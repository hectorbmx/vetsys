<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public Tenant $tenant;
    public string $activationUrl;
    public string $activationCode;

    public function __construct(Tenant $tenant, string $activationUrl, string $activationCode)
    {
        $this->tenant = $tenant;
        $this->activationUrl = $activationUrl;
        $this->activationCode = $activationCode;
    }

    public function build()
    {
        return $this->subject('Activa tu cuenta en VetSys')
            ->view('emails.tenants.activation');
    }
}
