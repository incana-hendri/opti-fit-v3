<?php

namespace App\Mail;

use App\Models\ClassSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Email C11 (docs/06): the admin cancelled a session; one email per household (BR-7.8).
class SessionCancelled extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ClassSession $session, public int $creditsReturned) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Class cancelled: {$this->session->title}, ".$this->session->starts_at->format('D j M, H:i'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer.session-cancelled');
    }
}
