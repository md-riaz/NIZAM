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

        $query = User::with('tenant');

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        return UserResource::collection($query->paginate(15));
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load('tenant', 'permissions'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'sometimes|string|in:admin,user',
            'tenant_id' => 'nullable|exists:tenants,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'user',
            'tenant_id' => $validated['tenant_id'] ?? null,
        ]);

        return response()->json(new UserResource($user), 201);
    }

    public function update(Request $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|unique:users,email,'.$user->id,
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|string|in:admin,user',
            'tenant_id' => 'nullable|exists:tenants,id',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return new UserResource($user->fresh()->load('tenant'));
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
