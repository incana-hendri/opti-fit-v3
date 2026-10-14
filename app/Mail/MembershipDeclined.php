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

// Email C5 (docs/06): the admin declined the request (BR-4.6).
class MembershipDeclined extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Membership $membership) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'About your package request');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer.membership-declined', with: ['contactEmail' => Settings::current()->contact_email]);
    }
}
