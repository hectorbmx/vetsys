<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\TenantSubscription;
use Carbon\Carbon;
use Stripe\StripeClient;

class StripeTenantCheckoutService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            throw new \RuntimeException('STRIPE_SECRET no esta configurado.');
        }

        $this->stripe = new StripeClient($secret);
    }

    public function createPlanCheckout(Tenant $tenant, Plan $plan, string $successUrl, string $cancelUrl, ?int $createdBy = null)
{
    if (!$plan->stripe_price_id) {
        throw new \RuntimeException('Este plan aun no esta sincronizado con Stripe.');
    }

    $customerId = $this->ensureStripeCustomer($tenant);
    [$startsAt, $endsAt] = $this->estimatedPeriod($tenant, $plan);

    TenantPayment::where('tenant_id', $tenant->id)
        ->where('status', 'pending')
        ->where('payment_method', 'stripe_checkout')
        ->update(['status' => 'cancelled']);

    TenantSubscription::where('tenant_id', $tenant->id)
        ->where('status', 'pending')
        ->where('provider', 'stripe')
        ->update(['status' => 'cancelled']);

    $subscription = TenantSubscription::create([
        'tenant_id'             => $tenant->id,
        'plan_id'               => $plan->id,
        'provider'              => 'stripe',
        'provider_customer_id'  => $customerId,
        'status'                => 'pending',
        'starts_at'             => $startsAt,
        'ends_at'               => $endsAt,
        'created_by'            => $createdBy,
        'notes'                 => 'Checkout Stripe generado para plan SaaS.',
    ]);

    $payment = TenantPayment::create([
        'tenant_id'               => $tenant->id,
        'tenant_subscription_id'  => $subscription->id,
        'plan_id'                 => $plan->id,
        'provider'                => 'stripe',
        'amount'                  => $plan->price,
        'currency'                => $plan->currency ?? 'MXN',
        'status'                  => 'pending',
        'payment_method'          => 'stripe_checkout',
        'period_starts_at'        => $startsAt,
        'period_ends_at'          => $endsAt,
        'created_by'              => $createdBy,
        'notes'                   => 'Pendiente de confirmacion por webhook Stripe.',
    ]);

    $mode = in_array($plan->billing_period, ['monthly', 'yearly'], true) ? 'subscription' : 'payment';
    $metadata = [
        'flow'                    => 'saas_plan_checkout',
        'tenant_id'               => (string) $tenant->id,
        'plan_id'                 => (string) $plan->id,
        'tenant_subscription_id'  => (string) $subscription->id,
        'tenant_payment_id'       => (string) $payment->id,
    ];

    $payload = [
        'mode'       => $mode,
        'customer'   => $customerId,
        'line_items' => [[
            'price'    => $plan->stripe_price_id,
            'quantity' => 1,
        ]],
        'success_url' => $successUrl,
        'cancel_url'  => $cancelUrl,
        'metadata'    => $metadata,
    ];

    if ($mode === 'subscription') {
        $payload['subscription_data'] = ['metadata' => $metadata];
    } else {
        $payload['payment_intent_data'] = ['metadata' => $metadata];
    }

    $session = $this->stripe->checkout->sessions->create($payload);

    $payment->update([
        'provider_payment_id' => $session->id,
        'payment_reference'   => $session->url,
    ]);

    return $session;
}


private function ensureStripeCustomer(Tenant $tenant): string
{
    if ($tenant->stripe_customer_id) {
        try {
            $customer = $this->stripe->customers->retrieve($tenant->stripe_customer_id);

            if (!isset($customer->deleted) || !$customer->deleted) {
                return $customer->id;
            }
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            $tenant->update(['stripe_customer_id' => null]);
        }
    }

    $customer = $this->stripe->customers->create([
        'name'     => $tenant->business_name ?: $tenant->name,
        'email'    => $tenant->email,
        'metadata' => ['tenant_id' => (string) $tenant->id],
    ]);

    $tenant->update(['stripe_customer_id' => $customer->id]);

    return $customer->id;
}

    private function estimatedPeriod(Tenant $tenant, Plan $plan): array
    {
        $startsAt = $tenant->subscription_ends_at && $tenant->subscription_ends_at->isFuture()
            ? $tenant->subscription_ends_at->copy()->addDay()->startOfDay()
            : now()->startOfDay();

        $endsAt = match ($plan->billing_period) {
            'monthly' => $startsAt->copy()->addMonth()->subDay()->endOfDay(),
            'yearly' => $startsAt->copy()->addYear()->subDay()->endOfDay(),
            default => null,
        };

        return [$startsAt, $endsAt];
    }
}
