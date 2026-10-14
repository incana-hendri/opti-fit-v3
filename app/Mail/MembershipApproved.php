<?php

namespace App\Mail;

use App\Models\Membership;
use App\Models\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Email C4 (docs/06): the admin approved the request (BR-4.4).
class MembershipApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Membership $membership, public ?int $firstMonthCredits) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're in! Your OptiFit package is active");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer.membership-approved', with: ['paymentInstructions' => Settings::current()->payment_instructions]);
    }
}
