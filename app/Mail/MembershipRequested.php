<?php

namespace App\Mail;

use App\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Email C3 (docs/06): a customer's package request was received (BR-4.2).
class MembershipRequested extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Membership $membership) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "We've received your package request");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer.membership-requested');
    }
}
