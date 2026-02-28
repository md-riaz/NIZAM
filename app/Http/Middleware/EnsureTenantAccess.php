<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $routeTenant = $request->route('tenant');

        // Resolve model binding — route may pass a Tenant model or a raw ID.
        $tenant = $routeTenant instanceof \App\Models\Tenant
            ? $routeTenant
            : ($routeTenant !== null ? \App\Models\Tenant::find($routeTenant) : null);

        // Enforce tenant lifecycle: suspended/terminated tenants are blocked.
        if ($tenant && ! $tenant->isOperational()) {
            return response()->json([
                'message' => 'Tenant is '.$tenant->status.'.',
            ], 403);
        }

        if ($user->role === 'admin') {
            return $next($request);
        }

        $tenantId = $tenant?->id ?? $routeTenant;

        if ($tenantId === null) {
            return $next($request);
        }

        if (! $user->tenant_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->tenant_id !== $tenantId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
