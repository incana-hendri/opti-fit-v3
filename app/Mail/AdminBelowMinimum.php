<?php

namespace App\Mail;

use App\Models\ClassSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Email A5 (docs/06): today's sessions that are below their minimum number of participants.
class AdminBelowMinimum extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, ClassSession>  $sessions
     */
    public function __construct(public Collection $sessions) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Classes below minimum today');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.admin.below-minimum');
    }
}
