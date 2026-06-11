<?php

namespace App\Http\Middleware;

use App\Services\Auth\UserAccessSessionManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidWebAccessSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $manager = app(UserAccessSessionManager::class);
        $access = $user ? $manager->activeWebAccess($user, $request) : null;

        if ($user && ! $access && Auth::guard('web')->viaRemember() && $manager->planAllows($user, 'web')) {
            $request->session()->regenerate();
            $access = $manager->registerWeb($user, $request);
        }

        if (! $user || ! $manager->planAllows($user, 'web') || ! $access) {
            $manager->revokeCurrentWeb($request);
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => ! $user || ! $access
                    ? 'Esta sesion fue reemplazada por un inicio de sesion mas reciente.'
                    : 'Tu plan no incluye acceso web.',
            ]);
        }

        $manager->touch($access);

        return $next($request);
    }
}
