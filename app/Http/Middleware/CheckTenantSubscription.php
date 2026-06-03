<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user || !$user->tenant_id) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Tu usuario no tiene una empresa asignada.',
                ]);
        }

        $this->activateDueScheduledSubscription($tenant);
        $tenant->refresh();

        if (!$tenant->is_active || $tenant->status !== 'active') {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Tu empresa no está activa.',
                ]);
        }

        if (!$tenant->plan_id) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Tu empresa no tiene un plan activo.',
                ]);
        }

        if ($tenant->subscription_ends_at && now()->greaterThan($tenant->subscription_ends_at)) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'El plan de tu empresa ha vencido.',
                ]);
        }

        return $next($request);
    }

    private function activateDueScheduledSubscription($tenant): void
    {
        $subscription = $tenant->subscriptions()
            ->where('status', 'scheduled')
            ->where('starts_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->latest('starts_at')
            ->first();

        if (!$subscription) {
            return;
        }

        $subscription->update(['status' => 'active']);

        $tenant->update([
            'plan_id' => $subscription->plan_id,
            'subscription_ends_at' => $subscription->ends_at,
            'status' => 'active',
            'is_active' => true,
        ]);
    }
}
