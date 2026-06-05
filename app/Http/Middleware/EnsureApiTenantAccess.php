<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Tu usuario esta inactivo.',
            ], 403);
        }

        if ($user->hasRole('super-admin')) {
            return response()->json([
                'message' => 'La app movil solo esta disponible para usuarios de tenant.',
            ], 403);
        }

        $tenant = $user->tenant()->with('plan')->first();

        if (!$tenant) {
            return response()->json([
                'message' => 'Tu usuario no tiene una empresa asignada.',
            ], 403);
        }

        $this->activateDueScheduledSubscription($tenant);
        $tenant->refresh()->load('plan');

        if (!$tenant->is_active || $tenant->status !== 'active') {
            return response()->json([
                'message' => 'Tu empresa no esta activa.',
            ], 403);
        }

        if (!$tenant->plan_id || !$tenant->plan || !$tenant->plan->is_active) {
            return response()->json([
                'message' => 'Tu empresa no tiene un plan activo.',
            ], 402);
        }

        if ($tenant->subscription_ends_at && now()->greaterThan($tenant->subscription_ends_at)) {
            return response()->json([
                'message' => 'El plan de tu empresa ha vencido.',
            ], 402);
        }

        if (!$tenant->subscription_ends_at && $tenant->trial_ends_at && now()->greaterThan($tenant->trial_ends_at)) {
            return response()->json([
                'message' => 'El periodo de prueba de tu empresa ha vencido.',
            ], 402);
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
