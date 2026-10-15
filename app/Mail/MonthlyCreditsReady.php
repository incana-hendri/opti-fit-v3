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

// Email C7 (docs/06): the monthly job granted this period's credits (BR-5.5).
class MonthlyCreditsReady extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Membership $membership, public string $period) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.Period::label($this->period).' credits are ready');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer.monthly-credits-ready');
    }
}
