<?php

namespace App\Services\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

class TenantSessionGuard
{
    private const BILLING_LIMITED_STATUSES = [
        'needs_review',
        'pending_payment',
        'trial_expired',
        'subscription_expired',
    ];

    public function canLogin(User $user): array
    {
        $tenant = $user->tenant()->with('plan')->first();

        if (! $tenant) {
            return $this->denied('Tu usuario no tiene una empresa asignada.', 'tenant_missing', 403);
        }

        $this->activateDueScheduledSubscription($tenant);
        $tenant->refresh()->load(['plan', 'subscriptions', 'payments']);

        if (! $tenant->is_active || $tenant->status !== 'active') {
            return $this->denied('Tu empresa no esta activa.', 'tenant_inactive', 403);
        }

        if (! $tenant->plan_id || ! $tenant->plan || ! $tenant->plan->is_active) {
            return $this->denied('Tu empresa no tiene un plan activo.', 'tenant_no_plan', 402);
        }

        $billing = $this->billingStatus($tenant);

        if (! $billing['allowed']) {
            return $billing;
        }

        return [
            'allowed' => true,
            'message' => null,
            'code' => $billing['code'],
            'billing_status' => $billing['billing_status'],
            'http_status' => 200,
        ];
    }

    public function canEnterBillingArea(User $user): array
    {
        $tenant = $user->tenant()->with('plan')->first();

        if (! $tenant) {
            return $this->denied('Tu usuario no tiene una empresa asignada.', 'tenant_missing', 403);
        }

        $this->activateDueScheduledSubscription($tenant);
        $tenant->refresh()->load(['plan', 'subscriptions', 'payments']);

        if (! $tenant->is_active || $tenant->status !== 'active') {
            return $this->denied('Tu empresa no esta activa.', 'tenant_inactive', 403);
        }

        if (! $tenant->plan_id || ! $tenant->plan || ! $tenant->plan->is_active) {
            return $this->denied('Tu empresa no tiene un plan activo.', 'tenant_no_plan', 402);
        }

        $billing = $this->billingStatus($tenant);

        if ($billing['allowed']) {
            return $billing + ['billing_limited' => false];
        }

        if (in_array($billing['billing_status'], self::BILLING_LIMITED_STATUSES, true)) {
            return [
                'allowed' => true,
                'message' => $billing['message'],
                'code' => $billing['code'],
                'billing_status' => $billing['billing_status'],
                'http_status' => 200,
                'billing_limited' => true,
            ];
        }

        return $billing + ['billing_limited' => false];
    }

    public function billingStatus(Tenant $tenant): array
    {
        $subscriptions = $tenant->subscriptions instanceof Collection
            ? $tenant->subscriptions
            : $tenant->subscriptions()->get();
        $payments = $tenant->payments instanceof Collection
            ? $tenant->payments
            : $tenant->payments()->get();

        $activeSubscription = $subscriptions
            ->where('status', 'active')
            ->filter(fn ($subscription) => ! $this->isExpired($subscription->ends_at))
            ->sortByDesc(fn ($subscription) => $subscription->starts_at ?? $subscription->created_at)
            ->first();

        $pendingSubscription = $subscriptions->where('status', 'pending')->first();
        $pendingPayment = $payments->where('status', 'pending')->first();

        if (! $activeSubscription) {
            if ($pendingSubscription || $pendingPayment) {
                return $this->denied(
                    'Tu plan esta pendiente de pago.',
                    'tenant_payment_required',
                    402,
                    'pending_payment'
                );
            }

            if ($tenant->trial_ends_at && $tenant->trial_ends_at->isPast()) {
                return $this->denied(
                    'El periodo de prueba de tu empresa ha vencido.',
                    'tenant_trial_expired',
                    402,
                    'trial_expired'
                );
            }

            if ($tenant->subscription_ends_at && $tenant->subscription_ends_at->isPast()) {
                return $this->denied(
                    'El plan de tu empresa ha vencido.',
                    'tenant_subscription_expired',
                    402,
                    'subscription_expired'
                );
            }

            return $this->denied(
                'Tu empresa tiene un plan asignado, pero no tiene una suscripcion activa.',
                'tenant_subscription_required',
                402,
                'needs_review'
            );
        }

        $periodEnd = $activeSubscription->ends_at;
        $paidPaymentsForPeriod = $payments
            ->where('status', 'paid')
            ->filter(function ($payment) use ($activeSubscription, $periodEnd) {
                if ((int) $payment->tenant_subscription_id === (int) $activeSubscription->id) {
                    return ! $this->isExpired($payment->period_ends_at ?? $periodEnd);
                }

                if (! $payment->period_ends_at) {
                    return false;
                }

                return ! $this->isExpired($payment->period_ends_at);
            });

        $trialPayment = $paidPaymentsForPeriod->first(fn ($payment) => $this->isTrialPayment($payment));

        if ($trialPayment) {
            if ($tenant->trial_ends_at && $tenant->trial_ends_at->isPast()) {
                return $this->denied(
                    'El periodo de prueba de tu empresa ha vencido.',
                    'tenant_trial_expired',
                    402,
                    'trial_expired'
                );
            }

            return [
                'allowed' => true,
                'message' => null,
                'code' => 'tenant_trial_active',
                'billing_status' => 'trial_active',
                'http_status' => 200,
            ];
        }

        if ($paidPaymentsForPeriod->isNotEmpty()) {
            return [
                'allowed' => true,
                'message' => null,
                'code' => 'tenant_paid_active',
                'billing_status' => 'paid_active',
                'http_status' => 200,
            ];
        }

        if ($pendingSubscription || $pendingPayment) {
            return $this->denied(
                'Tu plan esta pendiente de pago.',
                'tenant_payment_required',
                402,
                'pending_payment'
            );
        }

        return $this->denied(
            'Tu empresa tiene una suscripcion activa sin pago registrado.',
            'tenant_payment_required',
            402,
            'needs_review'
        );
    }

    private function denied(string $message, string $code, int $httpStatus, ?string $billingStatus = null): array
    {
        return [
            'allowed' => false,
            'message' => $message,
            'code' => $code,
            'billing_status' => $billingStatus ?? $code,
            'http_status' => $httpStatus,
        ];
    }

    private function isTrialPayment($payment): bool
    {
        return $payment->status === 'paid'
            && $payment->payment_method === 'trial'
            && (float) $payment->amount === 0.0;
    }

    private function isExpired($date): bool
    {
        return $date && now()->greaterThan($date);
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
