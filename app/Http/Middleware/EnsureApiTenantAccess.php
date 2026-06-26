<?php

namespace App\Http\Middleware;

use App\Services\Auth\TenantSessionGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Tu usuario esta inactivo.',
            ], 403);
        }

        if ($user->hasRole('super-admin')) {
            return response()->json([
                'message' => 'La app movil solo esta disponible para usuarios de tenant.',
            ], 403);
        }

        $access = app(TenantSessionGuard::class)->canLogin($user);

        if (! $access['allowed']) {
            return response()->json([
                'message' => $access['message'],
                'code' => $access['code'],
                'billing_status' => $access['billing_status'],
            ], $access['http_status'] ?? 403);
        }

        return $next($request);
    }
}
