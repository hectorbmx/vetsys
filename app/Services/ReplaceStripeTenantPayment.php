<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\TenantSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\StripeClient;

class ReplaceStripeTenantPayment
{
    public function replace(Tenant $tenant, TenantPayment $payment, array $data, int $actorId): TenantPayment
    {
        if ($payment->tenant_id !== $tenant->id || $payment->provider !== 'stripe'
            || $payment->payment_method !== 'stripe_checkout'
            || ! ($payment->status === 'pending' || ($payment->status === 'cancelled' && $payment->cancellation_reason === 'stripe_checkout_expired'))
            || ! $payment->provider_payment_id) {
            throw ValidationException::withMessages(['payment' => 'El pago Stripe ya no es sustituible.']);
        }

        $stripe = new StripeClient(config('services.stripe.secret'));
        $session = $stripe->checkout->sessions->retrieve($payment->provider_payment_id);
        if ($session->id !== $payment->provider_payment_id
            || ($session->metadata->flow ?? null) !== 'saas_plan_checkout'
            || (string) ($session->metadata->tenant_payment_id ?? '') !== (string) $payment->id
            || (string) ($session->metadata->tenant_id ?? '') !== (string) $tenant->id
            || ($session->payment_status ?? null) === 'paid'
            || ! in_array($session->status ?? null, ['open', 'expired'], true)) {
            throw ValidationException::withMessages(['payment' => 'Revisa el estado del cobro en Stripe antes de continuar.']);
        }
        if ($session->status === 'open') {
            $session = $stripe->checkout->sessions->expire($session->id);
        }
        if ($session->status !== 'expired' || ($session->payment_status ?? null) === 'paid'
            || ! empty($session->subscription)) {
            throw ValidationException::withMessages(['payment' => 'No se pudo confirmar que la sesión Stripe quedó sin cobro ni suscripción.']);
        }

        return DB::transaction(function () use ($tenant, $payment, $data, $actorId) {
            $locked = TenantPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->tenant_id !== $tenant->id || $locked->provider !== 'stripe'
                || $locked->payment_method !== 'stripe_checkout'
                || ! ($locked->status === 'pending' || ($locked->status === 'cancelled' && $locked->cancellation_reason === 'stripe_checkout_expired'))
                || TenantPayment::where('replaces_tenant_payment_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['payment' => 'El pago ya fue atendido por otra operación.']);
            }
            $subscription = TenantSubscription::whereKey($locked->tenant_subscription_id)->lockForUpdate()->first();
            if (! $subscription || $subscription->tenant_id !== $tenant->id || $subscription->provider !== 'stripe'
                || ! in_array($subscription->status, ['pending', 'cancelled'], true)
                || ($subscription->status === 'cancelled' && $locked->cancellation_reason !== 'stripe_checkout_expired')
                || $subscription->provider_subscription_id) {
                throw ValidationException::withMessages(['payment' => 'La suscripción Stripe requiere conciliación.']);
            }

            $startsAt = Carbon::parse($data['starts_at'])->startOfDay();
            $endsAt = Carbon::parse($data['ends_at'])->endOfDay();
            $paidAt = Carbon::parse($data['paid_at']);
            $locked->update([
                'status' => 'cancelled', 'cancelled_at' => $locked->cancelled_at ?? now(), 'cancelled_by' => $actorId,
                'cancellation_reason' => 'replaced_by_manual_payment',
            ]);
            $subscription->update(['status' => 'cancelled', 'cancelled_at' => $subscription->cancelled_at ?? now()]);

            $manualSubscription = TenantSubscription::create([
                'tenant_id' => $tenant->id, 'plan_id' => $locked->plan_id, 'provider' => 'manual',
                'status' => $startsAt->isFuture() ? 'scheduled' : 'active',
                'starts_at' => $startsAt, 'ends_at' => $endsAt,
                'created_by' => $actorId, 'notes' => 'Sustituye checkout Stripe del pago #' . $locked->id,
            ]);
            $manual = TenantPayment::create([
                'tenant_id' => $tenant->id, 'tenant_subscription_id' => $manualSubscription->id,
                'plan_id' => $locked->plan_id, 'provider' => 'manual',
                'replaces_tenant_payment_id' => $locked->id, 'amount' => $data['amount'],
                'currency' => $locked->currency, 'status' => 'paid',
                'payment_method' => $data['payment_method'], 'payment_reference' => $data['payment_reference'] ?? null,
                'paid_at' => $paidAt, 'period_starts_at' => $startsAt, 'period_ends_at' => $endsAt,
                'created_by' => $actorId, 'notes' => $data['notes'] ?? null,
            ]);
            $tenant->update([
                'plan_id' => $locked->plan_id, 'subscription_ends_at' => $endsAt,
                'trial_ends_at' => null,
                'status' => $tenant->activated_at && ! in_array($tenant->status, ['suspended', 'cancelled'], true) ? 'active' : $tenant->status,
                'is_active' => $tenant->activated_at && ! in_array($tenant->status, ['suspended', 'cancelled'], true) ? true : $tenant->is_active,
            ]);

            return $manual;
        });
    }
}
