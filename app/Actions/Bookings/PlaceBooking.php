<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\CreditEntryType;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\Period;

// BR-7.3, 6.3: writes one member's booking and, when it is charged, its credit entry. Only called by BookSession and AdminBook,
// inside their transaction with the household and session locked.
class PlaceBooking
{
    public function handle(Household $household, ClassSession $session, Member $member, User $by, bool $chargeCredit = true): Booking
    {
        $booking = $session->bookings()->create([
            'member_id' => $member->id,
            'household_id' => $household->id,
            'status' => BookingStatus::Booked,
            'credit_charged' => $chargeCredit,
            'booked_by' => $by->id,
        ]);

        if ($chargeCredit) {
            $when = $session->starts_at->format('D j M H:i');
            CreditLedger::write($household, Period::current(), CreditEntryType::Booking, -1, "Booked {$member->first_name} – {$when}", bookingId: $booking->id, by: $by);
        }

        return $booking->setRelation('member', $member);
    }
}
