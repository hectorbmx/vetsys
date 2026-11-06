<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantNotification;
use Carbon\Carbon;

class PendingPaymentReminderService
{
    public const TYPE = 'saas_pending_payment_reminder';

    public function notifyIfNeeded(Tenant $tenant, ?Carbon $localDate = null): bool
    {
        $localDate ??= now('America/Mexico_City');
        $reminderDate = $localDate->toDateString();

        $tenant->loadMissing(['plan', 'payments', 'subscriptions']);

        $pendingPayment = $tenant->payments
            ->where('status', 'pending')
            ->filter(fn ($payment) => ! $payment->period_ends_at || $payment->period_ends_at->greaterThanOrEqualTo($localDate))
            ->sortByDesc('created_at')
            ->first();
        $pendingSubscription = $tenant->subscriptions
            ->where('status', 'pending')
            ->filter(fn ($subscription) => ! $subscription->ends_at || $subscription->ends_at->greaterThanOrEqualTo($localDate))
            ->sortByDesc('created_at')
            ->first();

        if (! $pendingPayment && ! $pendingSubscription) {
            return false;
        }

        $alreadyNotified = TenantNotification::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', self::TYPE)
            ->where('data->reminder_date', $reminderDate)
            ->exists();

        if ($alreadyNotified) {
            return false;
        }

        $plan = $pendingPayment?->plan ?? $pendingSubscription?->plan ?? $tenant->plan;

        TenantNotification::create([
            'tenant_id' => $tenant->id,
            'type' => self::TYPE,
            'title' => 'Tu plan está pendiente de pago',
            'body' => 'El plan ' . ($plan?->name ?? 'actual') . ' sigue pendiente de pago. Completa el pago o comunícate con Master para habilitar el acceso operativo.',
            'url' => route('client.profile.index'),
            'data' => [
                'reminder_date' => $reminderDate,
                'tenant_payment_id' => $pendingPayment?->id,
                'tenant_subscription_id' => $pendingSubscription?->id,
                'plan_id' => $plan?->id,
            ],
        ]);

        return true;
    }
}
