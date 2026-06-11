<?php

namespace App\Http\Middleware;

use App\Services\Auth\UserAccessSessionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidMobileAccessSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $manager = app(UserAccessSessionManager::class);
        $access = $user ? $manager->activeMobileAccess($user, $token?->id) : null;

        if (! $user || ! $manager->planAllows($user, 'mobile') || ! $access) {
            $manager->revokeCurrentMobile($token?->id);
            $token?->delete();

            return response()->json([
                'message' => ! $user || ! $access
                    ? 'Esta sesion fue reemplazada por un inicio de sesion mas reciente.'
                    : 'Tu plan no incluye acceso a la app movil.',
            ], 401);
        }

        $manager->touch($access);

        return $next($request);
    }
}
