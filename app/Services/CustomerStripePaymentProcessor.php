<?php

namespace App\Services;

use App\Models\CustomerPaymentLink;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\TenantNotification;
use Illuminate\Support\Facades\DB;

class CustomerStripePaymentProcessor
{
    public function process(
        CustomerPaymentLink $paymentLink,
        string $providerPaymentId,
        ?string $providerSessionId = null
    ): ?Payment {
        return DB::transaction(function () use ($paymentLink, $providerPaymentId, $providerSessionId) {
            $paymentLink = CustomerPaymentLink::with('customer')->lockForUpdate()->find($paymentLink->id);

            if (!$paymentLink || !$paymentLink->customer) {
                return null;
            }

            $existingPayment = Payment::where('provider', 'stripe')
                ->where('provider_payment_id', $providerPaymentId)
                ->first();

            if ($existingPayment) {
                $paymentLink->update([
                    'status' => 'paid',
                    'stripe_payment_intent_id' => $providerPaymentId,
                    'stripe_checkout_session_id' => $providerSessionId ?: $paymentLink->stripe_checkout_session_id,
                    'paid_at' => now(),
                ]);

                return $existingPayment;
            }

            $isAdditionalPayment = $paymentLink->status === 'paid';
            $paymentMethodId = $paymentLink->payment_method_id
                ?: $this->cardPaymentMethodIdForTenant($paymentLink->tenant_id);

            if (!$paymentMethodId) {
                return null;
            }

            $payment = app(CustomerPaymentService::class)->apply(
                $paymentLink->customer,
                $paymentMethodId,
                (float) $paymentLink->amount,
                [
                    'provider' => 'stripe',
                    'provider_payment_id' => $providerPaymentId,
                    'provider_session_id' => $providerSessionId,
                    'status' => 'paid',
                    'reference' => 'Stripe PaymentIntent ' . $providerPaymentId,
                ]
            );

            $applied = (float) $payment->notes()->sum('note_payments.amount_applied');
            $credit = max((float) $payment->amount - $applied, 0);

            $paymentLink->update([
                'status' => 'paid',
                'stripe_payment_intent_id' => $providerPaymentId,
                'stripe_checkout_session_id' => $providerSessionId ?: $paymentLink->stripe_checkout_session_id,
                'paid_at' => now(),
            ]);

            TenantNotification::create([
                'tenant_id' => $paymentLink->tenant_id,
                'type' => 'customer_account_payment_paid',
                'title' => $isAdditionalPayment ? 'Pago adicional recibido' : 'Pago de cuenta recibido',
                'body' => $paymentLink->customer->full_name
                    . ' pago $' . number_format((float) $paymentLink->amount, 2) . ' MXN con Stripe.'
                    . ($credit > 0 ? ' Saldo a favor: $' . number_format($credit, 2) . ' MXN.' : ''),
                'url' => route('client.customers.show', $paymentLink->customer),
                'data' => [
                    'customer_id' => $paymentLink->customer_id,
                    'payment_id' => $payment->id,
                    'customer_payment_link_id' => $paymentLink->id,
                    'stripe_checkout_session_id' => $providerSessionId,
                    'stripe_payment_intent_id' => $providerPaymentId,
                    'amount_applied' => $applied,
                    'credit_balance' => $credit,
                ],
            ]);

            return $payment;
        });
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
}
