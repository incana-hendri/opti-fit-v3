<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\ClassSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Email C8 (docs/06): a customer or the admin booked members into a session (BR-7.3, 6.3).
class BookingConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Booking>  $bookings
     */
    public function __construct(public ClassSession $session, public Collection $bookings, public int $creditsLeft) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Booked: {$this->session->title}, ".$this->session->starts_at->format('D j M, H:i'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer.booking-confirmed');
    }
}
