<?php

namespace App\Actions\Sessions;

use App\Enums\BookingStatus;
use App\Mail\SessionTimeChanged;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// BR-7.10: the admin edits a session. Bookings stay as they are; if the start time changed, every booked household gets email C12.
class RescheduleSession
{
    /**
     * @param  array<string, mixed>  $attributes  the editable session fields (BR-7.1)
     * @return int how many households were emailed
     */
    public function handle(ClassSession $session, array $attributes, User $admin): int
    {
        /** @var Carbon $before */
        [$session, $before] = DB::transaction(function () use ($session, $attributes) {
            // Booking locks the session row, so a capacity change can't slip in half-way through a booking (BR-7.6).
            $session = ClassSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $before = $session->starts_at->copy();
            $session->update($attributes);

            return [$session, $before];
        });

        if ($session->starts_at->equalTo($before)) {
            return 0;
        }

        $householdIds = Booking::query()->where('class_session_id', $session->id)->where('status', BookingStatus::Booked)
            ->distinct()->pluck('household_id');

        foreach (Household::query()->with('user')->findMany($householdIds) as $household) {
            Mail::to($household->user->email)->queue(new SessionTimeChanged($session, $before));
        }

        return $householdIds->count();
    }
}
