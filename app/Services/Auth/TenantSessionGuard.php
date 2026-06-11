<?php

namespace App\Services\Auth;

use App\Models\User;

class TenantSessionGuard
{
    public function canLogin(User $user): array
    {
        $tenant = $user->tenant()->with('plan')->first();

        if (! $tenant) {
            return $this->denied('Tu usuario no tiene una empresa asignada.');
        }

        $this->activateDueScheduledSubscription($tenant);
        $tenant->refresh()->load('plan');

        if (! $tenant->is_active || $tenant->status !== 'active') {
            return $this->denied('Tu empresa no esta activa.');
        }

        if (! $tenant->plan_id || ! $tenant->plan || ! $tenant->plan->is_active) {
            return $this->denied('Tu empresa no tiene un plan activo.');
        }

        if ($tenant->subscription_ends_at && now()->greaterThan($tenant->subscription_ends_at)) {
            return $this->denied('El plan de tu empresa ha vencido.');
        }

        if (! $tenant->subscription_ends_at && $tenant->trial_ends_at && now()->greaterThan($tenant->trial_ends_at)) {
            return $this->denied('El periodo de prueba de tu empresa ha vencido.');
        }

        return ['allowed' => true, 'message' => null];
    }

    private function denied(string $message): array
    {
        return ['allowed' => false, 'message' => $message];
    }

    private function activateDueScheduledSubscription($tenant): void
    {
        $subscription = $tenant->subscriptions()
            ->where('status', 'scheduled')
            ->where('starts_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->latest('starts_at')
            ->first();

        if (! $subscription) {
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
