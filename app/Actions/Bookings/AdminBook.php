<?php

namespace App\Actions\Bookings;

use App\Exceptions\BookingException;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use App\Support\CreditLedger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// BR-6.3, 6.4: the admin books members of any household, ignoring the cutoff and the month rule. Email C8.
class AdminBook
{
    public function __construct(private PlaceBooking $placeBooking) {}

    /**
     * @param  list<int>  $memberIds
     * @return Collection<int, Booking>
     */
    public function handle(Household $household, ClassSession $session, array $memberIds, bool $chargeCredit, bool $overCapacity, User $admin): Collection
    {
        $memberIds = array_values(array_unique($memberIds));

        if ($memberIds === []) {
            throw new BookingException('Choose who to book.');
        }

        try {
            [$bookings, $creditsLeft] = DB::transaction(function () use ($household, $session, $memberIds, $chargeCredit, $overCapacity, $admin) {
                $household = Household::whereKey($household->id)->lockForUpdate()->firstOrFail();
                $session = ClassSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

                $members = $this->assertBookable($household, $session, $memberIds, $chargeCredit, $overCapacity);
                $bookings = new Collection;

                foreach ($members as $member) {
                    $bookings->push($this->placeBooking->handle($household, $session, $member, $admin, $chargeCredit));
                }

                return [$bookings, CreditLedger::balance($household)];
            });
        } catch (UniqueConstraintViolationException) {
            throw new BookingException('One of them is already booked into this class.');
        }

        Mail::to($household->user->email)->queue(new BookingConfirmed($session, $bookings, $creditsLeft));

        return $bookings;
    }

    /**
     * @param  list<int>  $memberIds
     * @return Collection<int, Member>
     */
    private function assertBookable(Household $household, ClassSession $session, array $memberIds, bool $chargeCredit, bool $overCapacity): Collection
    {
        if (! $session->isScheduled()) {
            throw new BookingException('This class has been cancelled.');
        }

        // Docs/05: any non-archived member of the household, not only the covered ones.
        $members = $household->activeMembers()->whereKey($memberIds)->get();
        if ($members->count() !== count($memberIds)) {
            throw new BookingException("Those people aren't all active members of {$household->name}.");
        }

        $already = $session->bookings()->occupying()->whereIn('member_id', $memberIds)->with('member')->first();
        if ($already !== null) {
            throw new BookingException("{$already->member->first_name} is already booked into this class.");
        }

        if ($session->placesLeft() < $members->count() && ! $overCapacity) {
            throw new BookingException('This class is full. Tick "Book over capacity" to add them anyway.');
        }

        $balance = CreditLedger::balance($household);
        if ($chargeCredit && $balance < $members->count()) {
            throw new BookingException("{$household->name} has {$balance} ".str('credit')->plural($balance).' but this needs '.$members->count().'. Untick "Charge 1 credit each" or adjust their credits first.');
        }

        return $members;
    }
}
