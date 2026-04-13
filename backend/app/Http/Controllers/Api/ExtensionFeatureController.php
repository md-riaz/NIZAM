<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Extension;
use App\Models\Tenant;
use App\Services\ExtensionFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ExtensionFeatureController extends Controller
{
    public function __construct(
        protected ExtensionFeatureService $extensionFeatureService,
    ) {}

    public function update(Request $request, Tenant $tenant, Extension $extension): JsonResponse
    {
        if ($extension->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Extension not found.'], 404);
        }

        $this->authorize('update', $extension);

        $validated = $request->validate([
            'follow_me_enabled' => ['sometimes', 'boolean'],
            'follow_me_destination' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dnd_enabled' => ['sometimes', 'boolean'],
        ]);

        try {
            $extension = $this->extensionFeatureService->updateFeatures($extension, $validated);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'follow_me_destination' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'data' => [
                'id' => $extension->id,
                'tenant_id' => $extension->tenant_id,
                'follow_me_enabled' => $extension->follow_me_enabled,
                'follow_me_destination' => $extension->follow_me_destination,
                'dnd_enabled' => $extension->dnd_enabled,
            ],
        ]);
    }
}
