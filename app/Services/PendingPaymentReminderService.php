<?php

namespace App\Services;

use App\Mail\TenantPendingPaymentMail;
use App\Models\Tenant;
use App\Models\TenantNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

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

        $notification = TenantNotification::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', self::TYPE)
            ->where('data->reminder_date', $reminderDate)
            ->first();

        $plan = $pendingPayment?->plan ?? $pendingSubscription?->plan ?? $tenant->plan;

        $created = false;

        if (! $notification) {
            $notification = TenantNotification::create([
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

            $created = true;
        }

        $notificationData = $notification->data ?? [];
        $emailSentAt = $notificationData['email_sent_at'] ?? null;

        if (
            ! $emailSentAt
            && $pendingPayment
            && filter_var($tenant->email, FILTER_VALIDATE_EMAIL)
        ) {
            try {
                Mail::to($tenant->email)->send(new TenantPendingPaymentMail($tenant, $pendingPayment));

                $notification->update([
                    'data' => array_merge($notificationData, [
                        'email_sent_at' => now()->toIso8601String(),
                        'email_recipient' => $tenant->email,
                        'email_error' => null,
                    ]),
                ]);
            } catch (Throwable $exception) {
                report($exception);

                $notification->update([
                    'data' => array_merge($notificationData, [
                        'email_last_attempt_at' => now()->toIso8601String(),
                        'email_recipient' => $tenant->email,
                        'email_error' => class_basename($exception),
                    ]),
                ]);
            }
        }

        return $created;
    }

    public function sendManual(Tenant $tenant): void
    {
        $localDate = now('America/Mexico_City');

        $pendingPayment = $tenant->payments()
            ->with('plan')
            ->where('status', 'pending')
            ->where(function ($query) use ($localDate) {
                $query->whereNull('period_ends_at')
                    ->orWhere('period_ends_at', '>=', $localDate);
            })
            ->latest('created_at')
            ->first();

        if (! $pendingPayment) {
            throw new InvalidArgumentException('El cliente no tiene un pago pendiente vigente.');
        }

        if (! filter_var($tenant->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El cliente no tiene un correo válido registrado.');
        }

        Mail::to($tenant->email)->send(new TenantPendingPaymentMail($tenant, $pendingPayment));
    }
}
