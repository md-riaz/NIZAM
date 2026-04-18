<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $routeOrganization = $request->route('organization');

        // Resolve model binding — route may pass an Organization model or a raw ID.
        $organization = $routeOrganization instanceof \App\Models\Organization
            ? $routeOrganization
            : ($routeOrganization !== null ? \App\Models\Organization::find($routeOrganization) : null);

        // Enforce organization lifecycle: suspended/terminated organizations are blocked.
        if ($organization && ! $organization->isOperational()) {
            return response()->json([
                'message' => 'Organization is '.$organization->status.'.',
            ], 403);
        }

        if ($user->role === 'superadmin' && $user->organization_id === null) {
            return $next($request);
        }

        // Resolve the organization ID for access comparison.
        // When the organization model was found, use its ID; otherwise fall back to
        // the raw route parameter so the ownership check still runs.
        $organizationId = $organization?->id;

        if ($organizationId === null) {
            $organizationId = is_string($routeOrganization) ? $routeOrganization : null;
        }

        if ($organizationId === null) {
            return $next($request);
        }

        if (! $user->organization_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->organization_id !== $organizationId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
