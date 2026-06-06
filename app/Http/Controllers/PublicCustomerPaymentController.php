<?php

namespace App\Http\Controllers;

use App\Models\CustomerPaymentLink;
use App\Services\StripeCustomerPaymentService;
use App\Services\CustomerStripePaymentProcessor;

class PublicCustomerPaymentController extends Controller
{
    public function show(string $token)
    {
        $paymentLink = CustomerPaymentLink::where('token', $token)
            ->with(['tenant', 'customer'])
            ->firstOrFail();

        if (request('stripe_success') && $paymentLink->status === 'pending' && $paymentLink->stripe_checkout_session_id) {
            try {
                $session = app(StripeCustomerPaymentService::class)
                    ->retrieveCheckoutSession($paymentLink->stripe_checkout_session_id);

                if (($session->payment_status ?? null) === 'paid' && is_string($session->payment_intent ?? null)) {
                    app(CustomerStripePaymentProcessor::class)->process(
                        $paymentLink,
                        $session->payment_intent,
                        $session->id
                    );
                    $paymentLink->refresh();
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return view('public.customer-payments.show', compact('paymentLink'));
    }

    public function checkout(string $token)
    {
        $paymentLink = CustomerPaymentLink::where('token', $token)
            ->with(['tenant', 'customer'])
            ->firstOrFail();

        try {
            $session = app(StripeCustomerPaymentService::class)->createCheckoutSession($paymentLink);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'No se pudo abrir Stripe Checkout: ' . $exception->getMessage());
        }

        return redirect($session->url);
    }
}
