<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\CreditEntryType;
use App\Exceptions\BookingException;
use App\Mail\BookingCancelled;
use App\Mail\BookingCancelledByStudio;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Household;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\Cutoff;
use App\Support\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// BR-7.7, 7.11 (customer) and BR-6.2 (admin): cancels one booking. Emails C9 and C10.
class CancelBooking
{
    /**
     * A customer's cancellation is refunded only before the cutoff. An admin's follows $returnCredit, which defaults
     * to on before the cutoff and off after it (BR-6.2). Returns whether a credit went back to the household.
     */
    public function handle(Booking $booking, User $by, ?bool $returnCredit = null): bool
    {
        $creditReturned = DB::transaction(function () use ($booking, $by, $returnCredit) {
            $household = Household::whereKey($booking->household_id)->lockForUpdate()->firstOrFail();
            $session = ClassSession::whereKey($booking->class_session_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($booking->id)->firstOrFail();

            $this->assertCancellable($booking, $session, $household, $by);

            $beforeCutoff = ! Cutoff::hasPassed($session);
            $refund = $booking->credit_charged && ($by->isAdmin() ? ($returnCredit ?? $beforeCutoff) : $beforeCutoff);

            // BR-7.7: late means after the cutoff, whoever cancels. "Return credit" (BR-6.1) works on any unrefunded cancellation.
            $booking->update([
                'status' => BookingStatus::Cancelled,
                'late_cancellation' => ! $beforeCutoff,
                'cancelled_at' => now(),
                'cancelled_by' => $by->id,
            ]);

            if ($refund) {
                $this->refund($household, $booking, $session, $by);
            }

            return $refund;
        });

        $this->notify($booking, $by, $creditReturned);

        return $creditReturned;
    }

    private function assertCancellable(Booking $booking, ClassSession $session, Household $household, User $by): void
    {
        if (! $by->isAdmin() && $household->user_id !== $by->id) {
            throw new BookingException("That booking isn't yours to cancel.");
        }

        if ($booking->status !== BookingStatus::Booked) {
            throw new BookingException('This booking is already cancelled or finished.');
        }

        // BR-7.11
        if (! $by->isAdmin() && ! $session->starts_at->isFuture()) {
            throw new BookingException('This class has already started, so it can no longer be cancelled.');
        }
    }

    // BR-5.6, 5.7: the credit goes to the current period, once per booking.
    private function refund(Household $household, Booking $booking, ClassSession $session, User $by): void
    {
        $booking->loadMissing('member');

        CreditLedger::write(
            $household,
            Period::current(),
            CreditEntryType::Refund,
            1,
            "Refund: {$booking->member->first_name} – ".$session->starts_at->format('D j M H:i'),
            bookingId: $booking->id,
            key: "refund:{$booking->id}",
            by: $by,
        );
    }

    private function notify(Booking $booking, User $by, bool $creditReturned): void
    {
        $booking->load(['household.user', 'member', 'classSession']);
        $mailable = $by->isAdmin()
            ? new BookingCancelledByStudio($booking, $creditReturned)
            : new BookingCancelled($booking, $creditReturned);

        Mail::to($booking->household->user->email)->queue($mailable);
    }
}
