<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Email A1 (docs/06): a customer verified their email.
class NewSignUp extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New OptiFit sign-up: '.$this->user->fullName());
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.admin.new-sign-up');
    }
}
