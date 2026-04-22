<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlockedDestination;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BlockedDestinationController extends Controller
{
    public function index(Request $request)
    {
        $query = BlockedDestination::query();

        if ($request->has('organization_id')) {
            $query->where('organization_id', $request->organization_id);
        } elseif (!$request->user()->is_superadmin) { // Assuming a superadmin check exists
             // Basic multi-tenancy check if not superadmin (though these routes are currently admin prefixed)
        }

        return $query->orderByDesc('id')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => 'nullable|uuid|exists:organizations,id',
            'pattern' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $block = BlockedDestination::create($validated);

        return response()->json($block, Response::HTTP_CREATED);
    }

    public function show(BlockedDestination $blockedDestination)
    {
        return $blockedDestination;
    }

    public function update(Request $request, BlockedDestination $blockedDestination)
    {
        $validated = $request->validate([
            'organization_id' => 'sometimes|nullable|uuid|exists:organizations,id',
            'pattern' => 'sometimes|string',
            'description' => 'nullable|string',
        ]);

        $blockedDestination->update($validated);

        return $blockedDestination;
    }

    public function destroy(BlockedDestination $blockedDestination)
    {
        $blockedDestination->delete();

        return response()->noContent();
    }
}
