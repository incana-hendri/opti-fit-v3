<?php

namespace App\Mail;

use App\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Email A3 (docs/06): a customer cancelled their membership (BR-4.8).
class AdminMembershipEnding extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Membership $membership) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Membership ending: '.$this->membership->household->name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.admin.membership-ending');
    }
}
