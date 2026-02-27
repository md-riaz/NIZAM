<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for managing extensions scoped to a tenant.
 */
class ExtensionController extends Controller
{
    /**
     * List extensions for a tenant (paginated).
     */
    public function index(Tenant $tenant): JsonResponse
    {
        $extensions = $tenant->extensions()->paginate(15);

        return response()->json($extensions);
    }

    /**
     * Create a new extension for a tenant.
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'extension' => 'required|string',
            'password' => 'required|string|min:8',
            'directory_first_name' => 'required|string',
            'directory_last_name' => 'required|string',
            'effective_caller_id_name' => 'nullable|string',
            'effective_caller_id_number' => 'nullable|string',
            'outbound_caller_id_name' => 'nullable|string',
            'outbound_caller_id_number' => 'nullable|string',
            'voicemail_enabled' => 'boolean',
            'voicemail_pin' => 'nullable|string|digits_between:4,8',
            'is_active' => 'boolean',
        ]);

        // Extension must be unique within tenant
        $request->validate([
            'extension' => [
                function ($attribute, $value, $fail) use ($tenant) {
                    if ($tenant->extensions()->where('extension', $value)->exists()) {
                        $fail('The extension has already been taken for this tenant.');
                    }
                },
            ],
        ]);

        $extension = $tenant->extensions()->create($validated);

        return response()->json($extension, 201);
    }

    /**
     * Show a single extension.
     */
    public function show(Tenant $tenant, Extension $extension): JsonResponse
    {
        if ($extension->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        return response()->json($extension);
    }

    /**
     * Update an existing extension.
     */
    public function update(Request $request, Tenant $tenant, Extension $extension): JsonResponse
    {
        if ($extension->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $validated = $request->validate([
            'extension' => 'required|string',
            'password' => 'required|string|min:8',
            'directory_first_name' => 'required|string',
            'directory_last_name' => 'required|string',
            'effective_caller_id_name' => 'nullable|string',
            'effective_caller_id_number' => 'nullable|string',
            'outbound_caller_id_name' => 'nullable|string',
            'outbound_caller_id_number' => 'nullable|string',
            'voicemail_enabled' => 'boolean',
            'voicemail_pin' => 'nullable|string|digits_between:4,8',
            'is_active' => 'boolean',
        ]);

        // Extension must be unique within tenant (excluding current)
        $request->validate([
            'extension' => [
                function ($attribute, $value, $fail) use ($tenant, $extension) {
                    if ($tenant->extensions()->where('extension', $value)->where('id', '!=', $extension->id)->exists()) {
                        $fail('The extension has already been taken for this tenant.');
                    }
                },
            ],
        ]);

        $extension->update($validated);

        return response()->json($extension);
    }

    /**
     * Delete an extension.
     */
    public function destroy(Tenant $tenant, Extension $extension): JsonResponse
    {
        if ($extension->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $extension->delete();

        return response()->json(null, 204);
    }
}
