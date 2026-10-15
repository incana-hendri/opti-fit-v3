<?php

namespace App\Actions\Sessions;

use App\Enums\BookingStatus;
use App\Enums\CreditEntryType;
use App\Enums\SessionStatus;
use App\Exceptions\SessionException;
use App\Mail\SessionCancelled;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Household;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\Period;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// BR-7.8: the admin cancels a session. Every booked booking is cancelled and refunded, and each household gets email C11.
class CancelSession
{
    /**
     * @return int how many households were emailed
     */
    public function handle(ClassSession $session, ?string $reason, User $admin): int
    {
        $cancelled = DB::transaction(fn () => $this->cancel($session, filled($reason) ? trim((string) $reason) : null, $admin));
        $session->refresh();

        foreach ($cancelled as $householdId => $bookings) {
            $household = Household::query()->with('user')->findOrFail($householdId);

            Mail::to($household->user->email)->queue(new SessionCancelled($session, $bookings->where('credit_charged', true)->count()));
        }

        return count($cancelled);
    }

    /**
     * @return array<int, Collection<int, Booking>> the cancelled bookings per household id
     */
    private function cancel(ClassSession $session, ?string $reason, User $admin): array
    {
        // BR-7.6: every affected household in id order, then the session.
        $householdIds = Booking::query()->where('class_session_id', $session->id)->where('status', BookingStatus::Booked)
            ->distinct()->pluck('household_id');
        $households = Household::query()->whereKey($householdIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $session = ClassSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

        if (! $session->isScheduled()) {
            throw new SessionException('This class has already been cancelled.');
        }

        $bookings = $session->bookings()->where('status', BookingStatus::Booked)->with('member')->lockForUpdate()->get();

        if ($bookings->pluck('household_id')->unique()->diff($households->keys())->isNotEmpty()) {
            throw new SessionException('Someone booked while the class was being cancelled. Please try again.');
        }

        $session->update(['status' => SessionStatus::Cancelled, 'cancellation_reason' => $reason]);

        foreach ($bookings as $booking) {
            $this->cancelBooking($booking, $households[$booking->household_id], $session, $admin);
        }

        return $bookings->groupBy('household_id')->all();
    }

    // BR-7.8: the credit goes back when the booking was charged; BR-5.7 keeps it to once.
    private function cancelBooking(Booking $booking, Household $household, ClassSession $session, User $admin): void
    {
        $booking->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $admin->id]);

        if (! $booking->credit_charged) {
            return;
        }

        CreditLedger::write(
            $household,
            Period::current(),
            CreditEntryType::Refund,
            1,
            "Refund: class cancelled, {$booking->member->first_name} – ".$session->starts_at->format('D j M H:i'),
            bookingId: $booking->id,
            key: "refund:{$booking->id}",
            by: $admin,
        );
    }
}
