<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\NotePaymentLink;
use App\Models\CustomerPaymentLink;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\TenantPayment;
use App\Models\TenantSubscription;
use App\Models\AdminNotification;
use App\Services\CustomerPaymentService;
use App\Services\CustomerStripePaymentProcessor;
use App\Services\PortalNotificationService;
use App\Services\TenantOnboardingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (!$secret) {
            return response('Webhook secret not configured', 500);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);

\Log::info('STRIPE EVENT RECEIVED', [
            'type' => $event->type,
        ]);


        } catch (\Throwable $exception) {
            return response('Invalid webhook signature', 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
	    'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event->data->object),
            'invoice.paid' => $this->handleInvoicePaid($event->data->object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            default => null,
        };

        return response('ok');
    }

    private function handleCheckoutCompleted($session): void
    {
        if (($session->metadata->flow ?? null) === 'customer_account_payment') {
            $this->handleCustomerAccountCheckoutCompleted($session);
            return;
        }

        if (($session->metadata->flow ?? null) === 'customer_note_payment') {
            $this->handleCustomerNoteCheckoutCompleted($session);
            return;
        }

        if (($session->metadata->flow ?? null) !== 'saas_plan_checkout') {
            return;
        }

        $tenant = Tenant::find($session->metadata->tenant_id ?? null);
        $plan = Plan::find($session->metadata->plan_id ?? null);
        $subscription = TenantSubscription::find($session->metadata->tenant_subscription_id ?? null);
        $payment = TenantPayment::find($session->metadata->tenant_payment_id ?? null);

        if (!$tenant || !$plan || !$subscription || !$payment) {
            return;
        }

        $providerSubscriptionId = is_string($session->subscription ?? null) ? $session->subscription : null;
        $providerCustomerId = is_string($session->customer ?? null) ? $session->customer : $tenant->stripe_customer_id;

        $subscription->update([
            'provider_subscription_id' => $providerSubscriptionId,
            'provider_customer_id' => $providerCustomerId,
        ]);

        $payment->update([
            'provider_payment_id' => $session->id,
            'provider_invoice_id' => is_string($session->invoice ?? null) ? $session->invoice : $payment->provider_invoice_id,
            'status' => ($session->payment_status ?? null) === 'paid' ? 'paid' : 'pending',
            'paid_at' => ($session->payment_status ?? null) === 'paid' ? now() : $payment->paid_at,
        ]);

        if (($session->payment_status ?? null) === 'paid' && $payment->wasChanged('status')) {
            $this->notifySaasOfStripePayment($payment->refresh());
        }

        if ($providerSubscriptionId) {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $stripeSubscription = $stripe->subscriptions->retrieve($providerSubscriptionId);
            $this->activateFromStripeSubscription($tenant, $plan, $subscription, $stripeSubscription);
            return;
        }

        if (($session->payment_status ?? null) === 'paid') {
            $this->activateTenantPlan($tenant, $plan, $subscription, $payment->period_starts_at, $payment->period_ends_at);
        }
    }

    private function handleCustomerAccountCheckoutCompleted($session): void
    {
        if (($session->payment_status ?? null) !== 'paid') {
            return;
        }

        $paymentLink = CustomerPaymentLink::with(['customer', 'paymentMethod'])
            ->find($session->metadata->customer_payment_link_id ?? null);

        if (!$paymentLink || !$paymentLink->customer) {
            return;
        }

        if (is_string($session->payment_intent ?? null)) {
            $payment = app(CustomerStripePaymentProcessor::class)->process(
                $paymentLink,
                $session->payment_intent,
                $session->id
            );

            if ($payment && $paymentLink->tenant) {
                app(TenantOnboardingService::class)->reconcileSafely($paymentLink->tenant);
            }
        }
    }

    private function handleCustomerNoteCheckoutCompleted($session): void
    {
        if (($session->payment_status ?? null) !== 'paid') {
            return;
        }

        $paymentLink = NotePaymentLink::with(['note', 'tenant', 'customer', 'paymentMethod'])
            ->find($session->metadata->note_payment_link_id ?? null);

        if (!$paymentLink || $paymentLink->status === 'paid' || !$paymentLink->note) {
            return;
        }

        DB::transaction(function () use ($paymentLink, $session) {
            $paymentLink->refresh();

            if ($paymentLink->status === 'paid') {
                return;
            }

            $note = $paymentLink->note()->lockForUpdate()->first();

            if (!$note || $note->status === 'PAGADA') {
                $paymentLink->update([
                    'status' => 'paid',
                    'stripe_checkout_session_id' => $session->id,
                    'stripe_payment_intent_id' => is_string($session->payment_intent ?? null) ? $session->payment_intent : null,
                    'paid_at' => now(),
                ]);
                return;
            }

            $existingPayment = Payment::where('provider', 'stripe')
                ->where('provider_session_id', $session->id)
                ->first();

            if ($existingPayment) {
                return;
            }

            $paymentMethodId = $paymentLink->payment_method_id
                ?: $this->cardPaymentMethodIdForTenant($paymentLink->tenant_id);

            if (!$paymentMethodId) {
                return;
            }

            $amountToApply = min((float) $paymentLink->amount, max((float) $note->balance, 0));

            if ($amountToApply <= 0) {
                return;
            }

            $payment = Payment::create([
                'tenant_id' => $paymentLink->tenant_id,
                'customer_id' => $paymentLink->customer_id,
                'payment_method_id' => $paymentMethodId,
                'provider' => 'stripe',
                'provider_payment_id' => is_string($session->payment_intent ?? null) ? $session->payment_intent : null,
                'provider_session_id' => $session->id,
                'status' => 'paid',
                'amount' => $amountToApply,
                'reference' => 'Stripe Checkout ' . $session->id,
            ]);

            $note->payments()->attach($payment->id, [
                'amount_applied' => $amountToApply,
            ]);

            $note->refresh();

            if ($note->balance <= 0) {
                $note->update(['status' => 'PAGADA']);
            }

            $paymentLink->update([
                'status' => 'paid',
                'stripe_checkout_session_id' => $session->id,
                'stripe_payment_intent_id' => is_string($session->payment_intent ?? null) ? $session->payment_intent : null,
                'paid_at' => now(),
            ]);

            TenantNotification::create([
                'tenant_id' => $paymentLink->tenant_id,
                'type' => 'customer_note_payment_paid',
                'title' => 'Nota pagada con Stripe',
                'body' => ($paymentLink->customer?->full_name ?? 'Un cliente') . ' pago la nota ' . $note->folio . ' por $' . number_format($amountToApply, 2) . ' MXN.',
                'url' => route('client.ventas.show', $note),
                'data' => [
                    'note_id' => $note->id,
                    'customer_id' => $paymentLink->customer_id,
                    'payment_id' => $payment->id,
                    'note_payment_link_id' => $paymentLink->id,
                    'stripe_checkout_session_id' => $session->id,
                    'stripe_payment_intent_id' => is_string($session->payment_intent ?? null) ? $session->payment_intent : null,
                ],
            ]);

            app(PortalNotificationService::class)->notePaymentConfirmed(
                $note->fresh(['customer', 'details']),
                $payment,
                $amountToApply
            );
        });

        if ($paymentLink->tenant) {
            app(TenantOnboardingService::class)->reconcileSafely($paymentLink->tenant);
        }
    }

    private function handleInvoicePaid($invoice): void
    {
        $providerSubscriptionId = is_string($invoice->subscription ?? null) ? $invoice->subscription : null;

        if (!$providerSubscriptionId) {
            return;
        }

        $subscription = TenantSubscription::where('provider_subscription_id', $providerSubscriptionId)->first();

        if (!$subscription) {
            return;
        }

        $tenant = $subscription->tenant;
        $plan = $subscription->plan;

        if (!$tenant || !$plan) {
            return;
        }

        $periodStartsAt = isset($invoice->lines->data[0]->period->start)
            ? Carbon::createFromTimestamp($invoice->lines->data[0]->period->start)
            : $subscription->starts_at;
        $periodEndsAt = isset($invoice->lines->data[0]->period->end)
            ? Carbon::createFromTimestamp($invoice->lines->data[0]->period->end)
            : $subscription->ends_at;

        $payment = TenantPayment::updateOrCreate(
            [
                'provider' => 'stripe',
                'provider_invoice_id' => $invoice->id,
            ],
            [
                'tenant_id' => $tenant->id,
                'tenant_subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'provider_payment_id' => is_string($invoice->payment_intent ?? null) ? $invoice->payment_intent : null,
                'amount' => ((int) ($invoice->amount_paid ?? 0)) / 100,
                'currency' => strtoupper($invoice->currency ?? $plan->currency ?? 'MXN'),
                'status' => 'paid',
                'payment_method' => 'stripe',
                'payment_reference' => $invoice->hosted_invoice_url ?? null,
                'paid_at' => isset($invoice->status_transitions->paid_at)
                    ? Carbon::createFromTimestamp($invoice->status_transitions->paid_at)
                    : now(),
                'period_starts_at' => $periodStartsAt,
                'period_ends_at' => $periodEndsAt,
                'notes' => 'Pago confirmado por webhook invoice.paid.',
            ]
        );

        if ($payment->wasRecentlyCreated || $payment->wasChanged('status')) {
            $this->notifySaasOfStripePayment($payment->refresh());
        }

        $this->activateTenantPlan($tenant, $plan, $subscription, $periodStartsAt, $periodEndsAt);
    }

    private function handleInvoicePaymentFailed($invoice): void
    {
        if (!is_string($invoice->subscription ?? null)) {
            return;
        }

        $subscription = TenantSubscription::where('provider_subscription_id', $invoice->subscription)->first();

        if (!$subscription) {
            return;
        }

        $subscription->update(['status' => 'past_due']);

        TenantPayment::updateOrCreate(
            [
                'provider' => 'stripe',
                'provider_invoice_id' => $invoice->id,
            ],
            [
                'tenant_id' => $subscription->tenant_id,
                'tenant_subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'amount' => ((int) ($invoice->amount_due ?? 0)) / 100,
                'currency' => strtoupper($invoice->currency ?? 'MXN'),
                'status' => 'failed',
                'payment_method' => 'stripe',
                'payment_reference' => $invoice->hosted_invoice_url ?? null,
                'notes' => 'Pago fallido reportado por Stripe.',
            ]
        );
    }

    private function handleSubscriptionUpdated($stripeSubscription): void
    {
        $subscription = TenantSubscription::where('provider_subscription_id', $stripeSubscription->id)->first();

        if (!$subscription) {
            return;
        }

        $status = $this->mapSubscriptionStatus($stripeSubscription->status ?? null);
        $endsAt = isset($stripeSubscription->current_period_end)
            ? Carbon::createFromTimestamp($stripeSubscription->current_period_end)
            : $subscription->ends_at;

        $subscription->update([
            'status' => $status,
            'ends_at' => $endsAt,
            'cancelled_at' => isset($stripeSubscription->canceled_at)
                ? Carbon::createFromTimestamp($stripeSubscription->canceled_at)
                : null,
        ]);

        if ($status === 'active') {
            $this->activateTenantPlan($subscription->tenant, $subscription->plan, $subscription, $subscription->starts_at, $endsAt);
        }
    }

    private function handleSubscriptionDeleted($stripeSubscription): void
    {
        $subscription = TenantSubscription::where('provider_subscription_id', $stripeSubscription->id)->first();

        if (!$subscription) {
            return;
        }

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => isset($stripeSubscription->current_period_end)
                ? Carbon::createFromTimestamp($stripeSubscription->current_period_end)
                : $subscription->ends_at,
        ]);
    }

    private function activateFromStripeSubscription(Tenant $tenant, Plan $plan, TenantSubscription $subscription, $stripeSubscription): void
    {
        $startsAt = isset($stripeSubscription->current_period_start)
            ? Carbon::createFromTimestamp($stripeSubscription->current_period_start)
            : $subscription->starts_at;
        $endsAt = isset($stripeSubscription->current_period_end)
            ? Carbon::createFromTimestamp($stripeSubscription->current_period_end)
            : $subscription->ends_at;

        $subscription->update([
            'status' => $this->mapSubscriptionStatus($stripeSubscription->status ?? null),
            'starts_at' => $startsAt,
            'trial_ends_at' => isset($stripeSubscription->trial_end)
                ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
                : null,
            'ends_at' => $endsAt,
        ]);

        if ($subscription->status === 'active') {
            $this->activateTenantPlan($tenant, $plan, $subscription, $startsAt, $endsAt);
        }
    }

    private function activateTenantPlan(Tenant $tenant, Plan $plan, TenantSubscription $subscription, $startsAt, $endsAt): void
    {
        $subscription->update([
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $tenant->update([
            'plan_id' => $plan->id,
            'subscription_ends_at' => $endsAt,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function mapSubscriptionStatus(?string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active', 'trialing' => 'active',
            'past_due', 'unpaid' => 'past_due',
            'canceled', 'incomplete_expired' => 'cancelled',
            default => 'pending',
        };
    }

    private function notifySaasOfStripePayment(TenantPayment $payment): void
    {
        if ($payment->status !== 'paid') {
            return;
        }

        $tenant = $payment->tenant ?? Tenant::find($payment->tenant_id);
        $plan = $payment->plan ?? Plan::find($payment->plan_id);

        AdminNotification::create([
            'actor_tenant_id' => $payment->tenant_id,
            'type' => 'stripe_saas_payment_paid',
            'title' => 'Pago Stripe recibido',
            'body' => ($tenant?->name ?? 'Un tenant') . ' pago el plan ' . ($plan?->name ?? 'SaaS') . ' por $' . number_format((float) $payment->amount, 2) . ' ' . ($payment->currency ?? 'MXN') . '.',
            'url' => $tenant ? route('admin.tenants.show', $tenant) : route('admin.notifications.index'),
            'data' => [
                'tenant_payment_id' => $payment->id,
                'tenant_id' => $payment->tenant_id,
                'plan_id' => $payment->plan_id,
                'provider_invoice_id' => $payment->provider_invoice_id,
                'provider_payment_id' => $payment->provider_payment_id,
            ],
        ]);
    }

    private function cardPaymentMethodIdForTenant(int $tenantId): ?int
    {
        return PaymentMethod::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->first(function (PaymentMethod $method) {
                $value = str($method->slug . ' ' . $method->name)->lower()->ascii()->toString();

                return str_contains($value, 'tarjeta')
                    || str_contains($value, 'tarteja')
                    || str_contains($value, 'card')
                    || str_contains($value, 'stripe');
            })?->id;
    }
private function handlePaymentIntentSucceeded($paymentIntent): void
{
    if (($paymentIntent->metadata->flow ?? null) === 'customer_account_payment') {
        $paymentLink = CustomerPaymentLink::find($paymentIntent->metadata->customer_payment_link_id ?? null);

        if ($paymentLink) {
            $payment = app(CustomerStripePaymentProcessor::class)->process($paymentLink, $paymentIntent->id);

            if ($payment && $paymentLink->tenant) {
                app(TenantOnboardingService::class)->reconcileSafely($paymentLink->tenant);
            }
        }

        return;
    }

    if (($paymentIntent->metadata->flow ?? null) !== 'customer_note_payment') {
        return;
    }

    $paymentLink = NotePaymentLink::with(['note', 'tenant', 'customer', 'paymentMethod'])
        ->find($paymentIntent->metadata->note_payment_link_id ?? null);

    if (!$paymentLink || $paymentLink->status === 'paid' || !$paymentLink->note) {
        return;
    }

    DB::transaction(function () use ($paymentLink, $paymentIntent) {
        $paymentLink->refresh();

        if ($paymentLink->status === 'paid') {
            return;
        }

        $note = $paymentLink->note()->lockForUpdate()->first();

        if (!$note || $note->status === 'PAGADA') {
            $paymentLink->update([
                'status' => 'paid',
                'stripe_payment_intent_id' => $paymentIntent->id,
                'paid_at' => now(),
            ]);
            return;
        }

        $existingPayment = Payment::where('provider', 'stripe')
            ->where('provider_payment_id', $paymentIntent->id)
            ->first();

        if ($existingPayment) {
            return;
        }

        $paymentMethodId = $paymentLink->payment_method_id
            ?: $this->cardPaymentMethodIdForTenant($paymentLink->tenant_id);

        if (!$paymentMethodId) {
            return;
        }

        $amountToApply = min((float) $paymentLink->amount, max((float) $note->balance, 0));

        if ($amountToApply <= 0) {
            return;
        }

        $payment = Payment::create([
            'tenant_id' => $paymentLink->tenant_id,
            'customer_id' => $paymentLink->customer_id,
            'payment_method_id' => $paymentMethodId,
            'provider' => 'stripe',
            'provider_payment_id' => $paymentIntent->id,
            'provider_session_id' => $paymentIntent->payment_details->order_reference ?? null,
            'status' => 'paid',
            'amount' => $amountToApply,
            'reference' => 'Stripe PaymentIntent ' . $paymentIntent->id,
        ]);

        $note->payments()->attach($payment->id, [
            'amount_applied' => $amountToApply,
        ]);

        $note->refresh();

        if ($note->balance <= 0) {
            $note->update(['status' => 'PAGADA']);
        }

        $paymentLink->update([
            'status' => 'paid',
            'stripe_payment_intent_id' => $paymentIntent->id,
            'paid_at' => now(),
        ]);

        TenantNotification::create([
            'tenant_id' => $paymentLink->tenant_id,
            'type' => 'customer_note_payment_paid',
            'title' => 'Nota pagada con Stripe',
            'body' => ($paymentLink->customer?->full_name ?? 'Un cliente') . ' pago la nota ' . $note->folio . ' por $' . number_format($amountToApply, 2) . ' MXN.',
            'url' => route('client.ventas.show', $note),
            'data' => [
                'note_id' => $note->id,
                'customer_id' => $paymentLink->customer_id,
                'payment_id' => $payment->id,
                'note_payment_link_id' => $paymentLink->id,
                'stripe_payment_intent_id' => $paymentIntent->id,
            ],
        ]);

        app(PortalNotificationService::class)->notePaymentConfirmed(
            $note->fresh(['customer', 'details']),
            $payment,
            $amountToApply
        );
    });

    if ($paymentLink->tenant) {
        app(TenantOnboardingService::class)->reconcileSafely($paymentLink->tenant);
    }
}
}
