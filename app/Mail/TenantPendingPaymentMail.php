<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\TenantPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantPendingPaymentMail extends Mailable
{
    use Queueable, SerializesModels;

    public Tenant $tenant;
    public TenantPayment $payment;
    public ?string $paymentUrl;

    public function __construct(Tenant $tenant, TenantPayment $payment)
    {
        $this->tenant = $tenant;
        $this->payment = $payment;
        $this->paymentUrl = filter_var($payment->payment_reference, FILTER_VALIDATE_URL)
            ? $payment->payment_reference
            : null;
    }

    public function build()
    {
        return $this->subject('Tu plan está pendiente de pago')
            ->view('emails.tenants.pending-payment');
    }
}
