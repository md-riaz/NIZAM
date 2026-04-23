<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

/**
 * API controller for admin user management.
 *
 * All endpoints are admin-only.
 */
class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $query = User::with(['organization', 'directPhoneNumbers']);
        $perPage = max(1, min($request->integer('per_page', 15), 500));

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->input('organization_id'));
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        return UserResource::collection($query->orderByDesc('id')->paginate($perPage));
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load('organization', 'permissions', 'directPhoneNumbers'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'sometimes|string|in:superadmin,admin,agent',
            'organization_id' => 'nullable|exists:organizations,id',
            'direct_phone_number_ids' => 'nullable|array',
            'direct_phone_number_ids.*' => 'uuid',
            'default_outbound_did_id' => 'nullable|uuid',
        ]);

        $organization = isset($validated['organization_id']) ? \App\Models\Organization::find($validated['organization_id']) : null;
        $directPhoneNumberIds = $validated['direct_phone_number_ids'] ?? [];
        if ($organization) {
            foreach ($directPhoneNumberIds as $didId) {
                if (! $organization->dids()->whereKey($didId)->exists()) {
                    return response()->json(['message' => 'Selected phone number must belong to the user organization.', 'errors' => ['direct_phone_number_ids' => ['Selected phone number must belong to the user organization.']]], 422);
                }
            }
        }

        if (! empty($validated['default_outbound_did_id']) && ! in_array($validated['default_outbound_did_id'], $directPhoneNumberIds, true)) {
            return response()->json(['message' => 'Default outbound number must be directly granted to the user.', 'errors' => ['default_outbound_did_id' => ['Default outbound number must be directly granted to the user.']]], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'agent',
            'organization_id' => $validated['organization_id'] ?? null,
            'default_outbound_did_id' => $validated['default_outbound_did_id'] ?? null,
        ]);

        $user->directPhoneNumbers()->sync($directPhoneNumberIds);

        return response()->json(new UserResource($user->load('directPhoneNumbers')), 201);
    }

    public function update(Request $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|unique:users,email,'.$user->id,
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|string|in:superadmin,admin,agent',
            'organization_id' => 'nullable|exists:organizations,id',
            'direct_phone_number_ids' => 'nullable|array',
            'direct_phone_number_ids.*' => 'uuid',
            'default_outbound_did_id' => 'nullable|uuid',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $nextOrganizationId = array_key_exists('organization_id', $validated) ? $validated['organization_id'] : $user->organization_id;
        $organization = $nextOrganizationId ? \App\Models\Organization::find($nextOrganizationId) : null;
        $directPhoneNumberIds = array_key_exists('direct_phone_number_ids', $validated)
            ? $validated['direct_phone_number_ids']
            : $user->directPhoneNumbers()->pluck('dids.id')->all();

        if ($organization) {
            foreach ($directPhoneNumberIds as $didId) {
                if (! $organization->dids()->whereKey($didId)->exists()) {
                    response()->json(['message' => 'Selected phone number must belong to the user organization.', 'errors' => ['direct_phone_number_ids' => ['Selected phone number must belong to the user organization.']]], 422)->throwResponse();
                }
            }
        }

        $nextDefaultDidId = array_key_exists('default_outbound_did_id', $validated)
            ? $validated['default_outbound_did_id']
            : $user->default_outbound_did_id;

        if ($nextDefaultDidId && ! in_array($nextDefaultDidId, $directPhoneNumberIds, true)) {
            response()->json(['message' => 'Default outbound number must be directly granted to the user.', 'errors' => ['default_outbound_did_id' => ['Default outbound number must be directly granted to the user.']]], 422)->throwResponse();
        }

        $user->update(collect($validated)->except(['direct_phone_number_ids'])->all());

        if (array_key_exists('direct_phone_number_ids', $validated)) {
            $user->directPhoneNumbers()->sync($validated['direct_phone_number_ids'] ?? []);
        }

        return new UserResource($user->fresh()->load('organization', 'directPhoneNumbers'));
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->tokens()->delete();
        $user->delete();

        return response()->json(null, 204);
    }

    /**
     * List permissions assigned to a user.
     */
    public function permissions(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'permissions' => $user->permissions->pluck('slug'),
        ]);
    }

    /**
     * Grant permissions to a user.
     */
    public function grantPermissions(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,slug',
        ]);

        $user->grantPermissions($validated['permissions']);

        return response()->json([
            'message' => 'Permissions granted.',
            'permissions' => $user->fresh()->permissions->pluck('slug'),
        ]);
    }

    /**
     * Revoke permissions from a user.
     */
    public function revokePermissions(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,slug',
        ]);

        $user->revokePermissions($validated['permissions']);

        return response()->json([
            'message' => 'Permissions revoked.',
            'permissions' => $user->fresh()->permissions->pluck('slug'),
        ]);
    }

    /**
     * List all available permissions.
     */
    public function availablePermissions(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $permissions = Permission::orderBy('module')->orderBy('slug')->get(['slug', 'description', 'module']);

        return response()->json(['permissions' => $permissions]);
    }
}
