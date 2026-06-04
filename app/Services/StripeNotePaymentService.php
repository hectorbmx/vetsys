<?php

namespace App\Services;

use App\Models\Note;
use App\Models\NotePaymentLink;
use Illuminate\Support\Str;
use Stripe\StripeClient;

class StripeNotePaymentService
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

    public function createLink(Note $note, ?int $paymentMethodId = null, int $expiresInHours = 24): NotePaymentLink
    {
        $note->loadMissing(['tenant', 'customer']);
        $balance = round((float) $note->balance, 2);

        if ($balance <= 0) {
            throw new \RuntimeException('La nota no tiene saldo pendiente.');
        }

        if (!$paymentMethodId) {
            throw new \RuntimeException('No hay un metodo de pago tipo tarjeta activo para registrar el cobro.');
        }

        return NotePaymentLink::create([
            'tenant_id' => $note->tenant_id,
            'note_id' => $note->id,
            'customer_id' => $note->customer_id,
            'payment_method_id' => $paymentMethodId,
            'token' => Str::random(64),
            'amount' => $balance,
            'currency' => 'MXN',
            'status' => 'pending',
            'expires_at' => now()->addHours($expiresInHours),
        ]);
    }

    public function createCheckoutSession(NotePaymentLink $paymentLink)
    {
        $paymentLink->loadMissing(['tenant', 'note', 'customer']);

        if (!$paymentLink->is_payable) {
            throw new \RuntimeException('Este link de pago ya no esta disponible.');
        }

        $amount = (int) round(((float) $paymentLink->amount) * 100);

        $sessionPayload = [
            'mode' => 'payment',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower($paymentLink->currency ?? 'MXN'),
                        'unit_amount' => $amount,
                        'product_data' => [
                            'name' => 'Nota ' . $paymentLink->note->folio,
                            'description' => $paymentLink->tenant->name . ' - ' . $paymentLink->customer->full_name,
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'success_url' => route('public.payments.show', $paymentLink->token) . '?stripe_success=1',
            'cancel_url' => route('public.payments.show', $paymentLink->token) . '?stripe_cancel=1',
            'metadata' => [
                'flow' => 'customer_note_payment',
                'tenant_id' => (string) $paymentLink->tenant_id,
                'note_id' => (string) $paymentLink->note_id,
                'customer_id' => (string) $paymentLink->customer_id,
                'note_payment_link_id' => (string) $paymentLink->id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'flow' => 'customer_note_payment',
                    'tenant_id' => (string) $paymentLink->tenant_id,
                    'note_id' => (string) $paymentLink->note_id,
                    'customer_id' => (string) $paymentLink->customer_id,
                    'note_payment_link_id' => (string) $paymentLink->id,
                ],
            ],
        ];

        if ($paymentLink->customer->email) {
            $sessionPayload['customer_email'] = $paymentLink->customer->email;
        }

        $session = $this->stripe->checkout->sessions->create($sessionPayload);

        $paymentLink->update([
            'stripe_checkout_session_id' => $session->id,
        ]);

        return $session;
    }
}
