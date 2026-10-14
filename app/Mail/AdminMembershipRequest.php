<?php

namespace App\Mail;

use App\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Email A2 (docs/06): a customer requested a package or a change (BR-4.2, 4.7).
class AdminMembershipRequest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Membership $membership) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Package request: '.$this->membership->household->name.' – '.$this->membership->package->name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.admin.membership-request');
    }
}
