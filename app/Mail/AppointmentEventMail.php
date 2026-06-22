<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentEventMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $mailData) {}

    public function build()
    {
        return $this->subject($this->mailData['subject'])
            ->view('emails.appointments.event');
    }
}
