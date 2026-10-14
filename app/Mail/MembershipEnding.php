<?php

namespace App\Mail;

use App\Models\Membership;
use App\Support\Period;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Email C13 (docs/06): the customer cancelled their membership (BR-4.8).
class MembershipEnding extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Membership $membership) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your membership ends on '.Period::lastDay((string) $this->membership->ends_period)->format('j F'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer.membership-ending');
    }
}
