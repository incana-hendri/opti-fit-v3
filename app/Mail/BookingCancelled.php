<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Email C9 (docs/06): a customer cancelled a booking (BR-7.7).
class BookingCancelled extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public bool $creditReturned) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Cancelled: {$this->booking->classSession->title}, ".$this->booking->classSession->starts_at->format('D j M, H:i'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer.booking-cancelled');
    }
}
