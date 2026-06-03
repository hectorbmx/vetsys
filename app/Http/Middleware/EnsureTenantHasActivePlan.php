<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantHasActivePlan
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->is_active) {
            auth()->logout();

            return redirect()
                ->route('login')
                ->with('error', 'Tu usuario está inactivo.');
        }

        // Super admin no necesita tenant ni plan.
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            auth()->logout();

            return redirect()
                ->route('login')
                ->with('error', 'Tu usuario no está asignado a un cliente.');
        }

        if (!$tenant->is_active || $tenant->status !== 'active') {
            auth()->logout();

            return redirect()
                ->route('login')
                ->with('error', 'La cuenta del cliente no está activa.');
        }

        if (!$tenant->plan_id || !$tenant->plan || !$tenant->plan->is_active) {
            auth()->logout();

            return redirect()
                ->route('login')
                ->with('error', 'El cliente no tiene un plan activo.');
        }

        return $next($request);
    }
}