<?php

namespace App\Actions\Bookings;

use App\Exceptions\BookingException;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Household;
use App\Models\Member;
use App\Models\Settings;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\Cutoff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// BR-7.2, 7.3, 7.6: a customer books one or more covered members into a session, all or nothing. Email C8.
class BookSession
{
    public function __construct(private PlaceBooking $placeBooking) {}

    /**
     * @param  list<int>  $memberIds
     * @return Collection<int, Booking>
     */
    public function handle(Household $household, ClassSession $session, array $memberIds, User $by): Collection
    {
        $memberIds = array_values(array_unique($memberIds));

        if ($memberIds === []) {
            throw new BookingException('Choose who you want to book.');
        }

        try {
            [$bookings, $creditsLeft] = DB::transaction(function () use ($household, $session, $memberIds, $by) {
                // BR-7.6: household first, then session. Every read that decides anything comes after both locks.
                $household = Household::whereKey($household->id)->lockForUpdate()->firstOrFail();
                $session = ClassSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

                $members = $this->assertBookable($household, $session, $memberIds);
                $bookings = new Collection;

                foreach ($members as $member) {
                    $bookings->push($this->placeBooking->handle($household, $session, $member, $by));
                }

                return [$bookings, CreditLedger::balance($household)];
            });
        } catch (UniqueConstraintViolationException) {
            // BR-7.5: the unique index is the last line of defence against a double booking.
            throw new BookingException('Someone just booked that place. Please check the class again.');
        }

        Mail::to($household->user->email)->queue(new BookingConfirmed($session, $bookings, $creditsLeft));

        return $bookings;
    }

    /**
     * @param  list<int>  $memberIds
     * @return Collection<int, Member>
     */
    private function assertBookable(Household $household, ClassSession $session, array $memberIds): Collection
    {
        $this->assertOpen($session);
        $members = $this->coveredMembers($household, $session, $memberIds);

        $placesLeft = $session->placesLeft();
        if ($placesLeft < $members->count()) {
            throw new BookingException($placesLeft <= 0 ? 'This class is full.' : "Only {$placesLeft} ".str('place')->plural($placesLeft).' left in this class.');
        }

        $balance = CreditLedger::balance($household);
        if ($balance < $members->count()) {
            throw new BookingException("You need {$members->count()} ".str('credit')->plural($members->count())." but have {$balance}.");
        }

        return $members;
    }

    // BR-7.2 items 1 to 3
    private function assertOpen(ClassSession $session): void
    {
        if (! $session->isScheduled()) {
            throw new BookingException('This class has been cancelled.');
        }

        if (Cutoff::hasPassed($session)) {
            throw new BookingException('Booking for this class has closed.');
        }

        if ($session->starts_at->gt(Cutoff::windowEnd())) {
            throw new BookingException($session->starts_at->isSameMonth(now())
                ? 'You can only book classes up to '.Settings::current()->booking_horizon_days.' days ahead.'
                : 'Classes in '.$session->starts_at->format('F').' can be booked once that month starts.');
        }
    }

    /**
     * BR-7.2 item 4: every selected member is covered (BR-4.10) and not already booked.
     *
     * @param  list<int>  $memberIds
     * @return Collection<int, Member>
     */
    private function coveredMembers(Household $household, ClassSession $session, array $memberIds): Collection
    {
        $covered = $household->coveredMembers()->keyBy('id');
        $members = new Collection;

        foreach ($memberIds as $memberId) {
            $members->push($covered[$memberId] ?? throw new BookingException(
                $covered->isEmpty() ? 'You need an active package to book a class.' : "That person isn't covered by your package.",
            ));
        }

        $already = $session->bookings()->occupying()->whereIn('member_id', $memberIds)->with('member')->first();

        if ($already !== null) {
            throw new BookingException("{$already->member->first_name} is already booked into this class.");
        }

        return $members;
    }
}
