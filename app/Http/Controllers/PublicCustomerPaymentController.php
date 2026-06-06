<?php

namespace App\Http\Controllers;

use App\Models\CustomerPaymentLink;
use App\Services\StripeCustomerPaymentService;

class PublicCustomerPaymentController extends Controller
{
    public function show(string $token)
    {
        $paymentLink = CustomerPaymentLink::where('token', $token)
            ->with(['tenant', 'customer'])
            ->firstOrFail();

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

            return back()->with('error', 'No se pudo abrir Stripe Checkout. Intenta de nuevo.');
        }

        return redirect($session->url);
    }
}
