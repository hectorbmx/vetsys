<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantSessionGuard
{
    public function canLogin(User $user): array
    {
        $tenant = $user->tenant()->with('plan')->first();

        if (!$tenant) {
            return [
                'allowed' => false,
                'message' => 'Tu usuario no tiene una empresa asignada.',
            ];
        }

        $this->activateDueScheduledSubscription($tenant);
        $tenant->refresh();

        if (!$tenant->plan) {
            return [
                'allowed' => false,
                'message' => 'Tu empresa no tiene un plan activo.',
            ];
        }

       if (!$tenant->is_active) {
    return [
        'allowed' => false,
        'message' => 'Tu empresa no está activa.',
    ];
}

if ($tenant->status !== 'active') {
    return [
        'allowed' => false,
        'message' => 'El estado de tu empresa no permite iniciar sesión.',
    ];
}

if (!$tenant->plan_id || !$tenant->plan) {
    return [
        'allowed' => false,
        'message' => 'Tu empresa no tiene un plan activo.',
    ];
}

if ($tenant->subscription_ends_at && now()->greaterThan($tenant->subscription_ends_at)) {
    return [
        'allowed' => false,
        'message' => 'El plan de tu empresa ha vencido.',
    ];
}

if (!$tenant->subscription_ends_at && $tenant->trial_ends_at && now()->greaterThan($tenant->trial_ends_at)) {
    return [
        'allowed' => false,
        'message' => 'El periodo de prueba de tu empresa ha vencido.',
    ];
}

        $maxUsers = (int) $tenant->plan->max_users;

        if ($maxUsers <= 0) {
            return [
                'allowed' => false,
                'message' => 'El plan de tu empresa no permite usuarios activos.',
            ];
        }

        $tenantUserIds = $tenant->users()->pluck('id');

        $activeSessions = DB::table('sessions')
            ->whereIn('user_id', $tenantUserIds)
            ->where('last_activity', '>=', now()->subMinutes(config('session.lifetime'))->timestamp)
            ->count();

        if ($activeSessions >= $maxUsers) {
            return [
                'allowed' => false,
                'message' => 'Ya tienes una sesión en uso. Cierra sesión en otro dispositivo para poder entrar.',
            ];
        }

        return [
            'allowed' => true,
            'message' => null,
        ];
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
