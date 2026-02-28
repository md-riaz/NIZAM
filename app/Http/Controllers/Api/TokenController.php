<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API controller for managing personal API tokens.
 */
class TokenController extends Controller
{
    /**
     * List the current user's API tokens.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->tokens,
        ]);
    }

    /**
     * Create a new personal access token.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'sometimes|array',
            'abilities.*' => 'string',
        ]);

        $token = $request->user()->createToken(
            $validated['name'],
            $validated['abilities'] ?? ['*']
        );

        return response()->json([
            'data' => $token->accessToken,
            'plainTextToken' => $token->plainTextToken,
        ], 201);
    }

    /**
     * Revoke (delete) a token by ID.
     */
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $token = $request->user()->tokens()->where('id', $tokenId)->first();

        if (! $token) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $token->delete();

        return response()->json(null, 204);
    }
}
