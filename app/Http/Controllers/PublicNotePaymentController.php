<?php

namespace App\Http\Controllers;

use App\Models\NotePaymentLink;
use App\Services\StripeNotePaymentService;

class PublicNotePaymentController extends Controller
{
    public function show(string $token)
    {
        $paymentLink = NotePaymentLink::query()
            ->where('token', $token)
            ->with(['tenant', 'note.details.catalogItem', 'note.details.animal', 'customer'])
            ->firstOrFail();

        return view('public.payments.show', compact('paymentLink'));
    }

    public function checkout(string $token)
    {
        $paymentLink = NotePaymentLink::query()
            ->where('token', $token)
            ->with(['tenant', 'note', 'customer'])
            ->firstOrFail();

        if (!$paymentLink->is_payable) {
            return redirect()
                ->route('public.payments.show', $paymentLink->token)
                ->with('error', 'Este link de pago ya no esta disponible.');
        }

        try {
            $session = app(StripeNotePaymentService::class)->createCheckoutSession($paymentLink);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('public.payments.show', $paymentLink->token)
                ->with('error', 'No se pudo abrir Stripe Checkout. Intenta de nuevo.');
        }

        return redirect($session->url);
    }
}
