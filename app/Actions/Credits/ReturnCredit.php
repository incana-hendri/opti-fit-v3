<?php

namespace App\Actions\Credits;

use App\Enums\CreditEntryType;
use App\Exceptions\CreditException;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditEntry;
use App\Models\Household;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\Period;
use Illuminate\Support\Facades\DB;

// BR-6.1, 5.7: the admin returns the credit of a charged booking that didn't get it back. At most once per booking.
class ReturnCredit
{
    public function handle(Booking $booking, User $admin): CreditEntry
    {
        return DB::transaction(function () use ($booking, $admin) {
            $household = Household::whereKey($booking->household_id)->lockForUpdate()->firstOrFail();
            $session = ClassSession::whereKey($booking->class_session_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($booking->id)->with('member')->firstOrFail();

            if (! $booking->canReturnCredit()) {
                throw new CreditException($this->reasonItCannot($booking));
            }

            // BR-5.6: refunds go to the current period.
            return CreditLedger::write(
                $household,
                Period::current(),
                CreditEntryType::Refund,
                1,
                "Refund: {$booking->member->first_name} – ".$session->starts_at->format('D j M H:i'),
                bookingId: $booking->id,
                key: "refund:{$booking->id}",
                by: $admin,
            ) ?? throw new CreditException('This booking has already been refunded.');
        });
    }

    private function reasonItCannot(Booking $booking): string
    {
        return match (true) {
            ! $booking->credit_charged => 'This booking was free, so there is no credit to return.',
            $booking->isRefunded() => 'This booking has already been refunded.',
            default => 'Cancel the booking first. A booking that is still booked has not used its credit up yet.',
        };
    }
}
