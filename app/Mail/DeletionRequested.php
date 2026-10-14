<?php

namespace App\Mail;

use App\Models\Household;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Email A4 (docs/06): a customer asked for their account to be deleted (BR-1.6).
class DeletionRequested extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Household $household) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Deletion request: '.$this->household->name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.admin.deletion-requested');
    }
}
