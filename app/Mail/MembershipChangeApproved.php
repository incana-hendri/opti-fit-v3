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

// Email C6 (docs/06): a change of package or members was approved (BR-4.7).
class MembershipChangeApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Membership $membership, public Membership $previous) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your package changes on 1 '.Period::label((string) $this->membership->starts_period));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer.membership-change-approved');
    }
}
