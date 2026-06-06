<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPaymentLink;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Stripe\StripeClient;

class StripeCustomerPaymentService
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

    public function createLink(Customer $customer, int $paymentMethodId, float $amount): CustomerPaymentLink
    {
        if ($amount < 10) {
            throw new \RuntimeException('Stripe requiere un monto minimo de $10.00 MXN para generar el link.');
        }

        return CustomerPaymentLink::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'payment_method_id' => $paymentMethodId,
            'token' => Str::random(64),
            'amount' => round($amount, 2),
            'currency' => 'MXN',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function createCheckoutSession(CustomerPaymentLink $paymentLink)
    {
        return Cache::lock('customer-payment-checkout:' . $paymentLink->id, 30)->block(10, function () use ($paymentLink) {
            $paymentLink->refresh()->loadMissing(['tenant', 'customer']);

            if (!$paymentLink->is_payable) {
                throw new \RuntimeException('Este link de pago ya no esta disponible.');
            }

            if ((float) $paymentLink->amount < 10) {
                throw new \RuntimeException('Stripe requiere un monto minimo de $10.00 MXN para abrir el checkout.');
            }

            if ($paymentLink->stripe_checkout_session_id) {
                $existingSession = $this->stripe->checkout->sessions->retrieve($paymentLink->stripe_checkout_session_id);

                if (($existingSession->payment_status ?? null) === 'paid') {
                    throw new \RuntimeException('Este link ya fue pagado.');
                }

                if (($existingSession->status ?? null) === 'open' && $existingSession->url) {
                    return $existingSession;
                }
            }

            $payload = [
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($paymentLink->currency),
                    'unit_amount' => (int) round(((float) $paymentLink->amount) * 100),
                    'product_data' => [
                        'name' => 'Pago de cuenta',
                        'description' => $paymentLink->tenant->name . ' - ' . $paymentLink->customer->full_name,
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => route('public.customer-payments.show', $paymentLink->token) . '?stripe_success=1',
            'cancel_url' => route('public.customer-payments.show', $paymentLink->token) . '?stripe_cancel=1',
            'metadata' => [
                'flow' => 'customer_account_payment',
                'customer_payment_link_id' => (string) $paymentLink->id,
                'tenant_id' => (string) $paymentLink->tenant_id,
                'customer_id' => (string) $paymentLink->customer_id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'flow' => 'customer_account_payment',
                    'customer_payment_link_id' => (string) $paymentLink->id,
                ],
            ],
            ];

            if ($paymentLink->customer->email) {
                $payload['customer_email'] = $paymentLink->customer->email;
            }

            $session = $this->stripe->checkout->sessions->create($payload);

            $paymentLink->update(['stripe_checkout_session_id' => $session->id]);

            return $session;
        });
    }

    public function retrieveCheckoutSession(string $sessionId)
    {
        return $this->stripe->checkout->sessions->retrieve($sessionId);
    }
}
