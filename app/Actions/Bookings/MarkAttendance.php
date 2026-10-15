<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Household;
use Illuminate\Support\Facades\DB;

// BR-7.9: the admin marks a booking attended or no-show from 30 minutes before the start. Credits are untouched.
class MarkAttendance
{
    /** BR-7.9 */
    private const MINUTES_BEFORE_START = 30;

    public function handle(Booking $booking, BookingStatus $status): Booking
    {
        if (! in_array($status, [BookingStatus::Attended, BookingStatus::NoShow], true)) {
            throw new BookingException('Attendance is either attended or no-show.');
        }

        return DB::transaction(function () use ($booking, $status) {
            Household::whereKey($booking->household_id)->lockForUpdate()->firstOrFail();
            $session = ClassSession::whereKey($booking->class_session_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($booking->id)->firstOrFail();

            if ($booking->status === BookingStatus::Cancelled) {
                throw new BookingException('A cancelled booking can\'t be marked.');
            }

            if (now()->lt($session->starts_at->copy()->subMinutes(self::MINUTES_BEFORE_START))) {
                throw new BookingException('Attendance can be marked from '.self::MINUTES_BEFORE_START.' minutes before the class starts.');
            }

            $booking->update(['status' => $status]);

            return $booking;
        });
    }
}
