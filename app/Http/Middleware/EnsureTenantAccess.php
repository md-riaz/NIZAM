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

        if ($user->role === 'admin') {
            return $next($request);
        }

        $tenantId = $request->route('tenant');

        if ($tenantId === null) {
            return $next($request);
        }

        // Resolve model binding — route may pass a Tenant model or a raw ID.
        $tenantId = $tenantId instanceof \App\Models\Tenant
            ? $tenantId->id
            : $tenantId;

        if (! $user->tenant_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->tenant_id !== $tenantId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
