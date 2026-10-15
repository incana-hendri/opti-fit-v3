<?php

namespace App\Mail;

use App\Models\ClassSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

// Email C12 (docs/06): the admin moved a session; one email per booked household (BR-7.10).
class SessionTimeChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ClassSession $session, public Carbon $previousStartsAt) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Class time changed: {$this->session->title}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer.session-time-changed');
    }
}
