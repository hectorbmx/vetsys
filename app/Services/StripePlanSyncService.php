<?php

namespace App\Services;

use App\Models\Plan;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripePlanSyncService
{
    public function __construct(private ?StripeClient $stripe = null)
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            throw new \RuntimeException('STRIPE_SECRET no esta configurado.');
        }

        $this->stripe ??= new StripeClient($secret);
    }

    /**
     * @throws ApiErrorException
     */
    public function sync(Plan $plan): Plan
    {
        $product = $this->syncProduct($plan);
        $price = $this->syncPrice($plan, $product->id);

        $plan->update([
            'stripe_product_id' => $product->id,
            'stripe_price_id' => $price->id,
        ]);

        return $plan->refresh();
    }

    /**
     * @throws ApiErrorException
     */
    private function syncProduct(Plan $plan)
    {
        $payload = [
            'name' => $plan->name,
            'description' => $plan->description,
            'active' => (bool) $plan->is_active,
            'metadata' => [
                'local_plan_id' => (string) $plan->id,
                'local_plan_slug' => (string) $plan->slug,
            ],
        ];

        if ($plan->stripe_product_id) {
            return $this->stripe->products->update($plan->stripe_product_id, $payload);
        }

        return $this->stripe->products->create($payload);
    }

    /**
     * @throws ApiErrorException
     */
    private function syncPrice(Plan $plan, string $productId)
    {
        $amount = (int) round(((float) $plan->price) * 100);
        $currency = strtolower($plan->currency ?? 'mxn');
        $recurring = $this->recurringPayload($plan->billing_period);

        if ($plan->stripe_price_id && $this->priceStillMatches($plan, $amount, $currency, $recurring)) {
            return $this->stripe->prices->retrieve($plan->stripe_price_id);
        }

        $payload = [
            'product' => $productId,
            'unit_amount' => $amount,
            'currency' => $currency,
            'metadata' => [
                'local_plan_id' => (string) $plan->id,
                'local_plan_slug' => (string) $plan->slug,
            ],
        ];

        if ($recurring) {
            $payload['recurring'] = $recurring;
        }

        return $this->stripe->prices->create($payload);
    }

    private function priceStillMatches(Plan $plan, int $amount, string $currency, ?array $recurring): bool
    {
        try {
            $price = $this->stripe->prices->retrieve($plan->stripe_price_id);
        } catch (ApiErrorException) {
            return false;
        }

        if ((int) $price->unit_amount !== $amount || strtolower($price->currency) !== $currency) {
            return false;
        }

        $expectedInterval = $recurring['interval'] ?? null;
        $actualInterval = $price->recurring?->interval ?? null;

        return $expectedInterval === $actualInterval;
    }

    private function recurringPayload(?string $billingPeriod): ?array
    {
        return match ($billingPeriod) {
            'monthly' => ['interval' => 'month'],
            'yearly' => ['interval' => 'year'],
            default => null,
        };
    }
}
