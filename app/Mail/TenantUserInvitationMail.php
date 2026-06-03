<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TenantUserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public Tenant $tenant;
    public string $invitationUrl;

    public function __construct(User $user, Tenant $tenant, string $invitationUrl)
    {
        $this->user = $user;
        $this->tenant = $tenant;
        $this->invitationUrl = $invitationUrl;
    }

    public function build()
    {
        return $this->subject('Invitación para acceder a VetSys')
            ->view('emails.tenants.invitation');
    }
}