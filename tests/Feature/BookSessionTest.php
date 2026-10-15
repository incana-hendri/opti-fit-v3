<?php

use App\Actions\Bookings\BookSession;
use App\Enums\BookingStatus;
use App\Enums\CreditEntryType;
use App\Exceptions\BookingException;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\CreditEntry;
use App\Models\GuestEntry;
use App\Models\Member;
use App\Models\Membership;
use App\Support\CreditLedger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

// Friday 16 Oct 2026. The Monday 19 Oct 05:00 class closes for booking on Sunday 18 Oct at 21:00.
beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $this->user = bookingCustomer();
    $this->household = $this->user->household;
    $this->session = classSession(['starts_at' => '2026-10-19 05:00']);
});

function book(object $test, ?array $memberIds = null, $session = null): void
{
    app(BookSession::class)->handle(
        $test->household,
        $session ?? $test->session,
        $memberIds ?? $test->household->members->pluck('id')->all(),
        $test->user,
    );
}

it('books one place per member and charges one credit each (BR-7.3)', function () {
    book($this);

    expect(Booking::query()->count())->toBe(2)
        ->and(Booking::query()->where('status', BookingStatus::Booked)->where('credit_charged', true)->count())->toBe(2)
        ->and(CreditLedger::balance($this->household))->toBe(6);

    $entry = CreditEntry::query()->where('type', CreditEntryType::Booking)->first();
    expect($entry->amount)->toBe(-1)
        ->and($entry->booking_id)->not->toBeNull()
        ->and($entry->description)->toStartWith('Booked ')->toEndWith('– Mon 19 Oct 05:00');
});

it('queues one confirmation for the whole booking (C8)', function () {
    book($this);

    Mail::assertQueued(BookingConfirmed::class, 1);
    Mail::assertQueued(BookingConfirmed::class, fn (BookingConfirmed $mail) => $mail->hasTo($this->user->email) && $mail->creditsLeft === 6 && $mail->bookings->count() === 2);
});

it('rejects a cancelled session (BR-7.2)', function () {
    $this->session->update(['status' => 'cancelled']);

    expect(fn () => book($this))->toThrow(BookingException::class, 'cancelled');
    expect(Booking::query()->count())->toBe(0);
});

it('closes booking at the cutoff: a 05:00 class closes at 21:00 the evening before (BR-7.2)', function () {
    $this->travelTo(Carbon::parse('2026-10-18 20:59'));
    book($this, [$this->household->members->first()->id]);

    $this->travelTo(Carbon::parse('2026-10-18 21:00'));
    expect(fn () => book($this, [$this->household->members->last()->id]))->toThrow(BookingException::class, 'closed');
});

it('uses the session cutoff override instead of the setting (D2)', function () {
    $this->session->update(['cutoff_hours' => 24]);

    $this->travelTo(Carbon::parse('2026-10-18 05:00'));
    expect(fn () => book($this))->toThrow(BookingException::class, 'closed');

    $this->travelTo(Carbon::parse('2026-10-18 04:59'));
    book($this);

    expect(Booking::query()->count())->toBe(2);
});

it('rejects a class in next month (BR-7.2)', function () {
    $session = classSession(['starts_at' => '2026-11-02 05:00']);
    $this->travelTo(Carbon::parse('2026-10-31 10:00'));

    expect(fn () => book($this, null, $session))->toThrow(BookingException::class, 'November');
});

it('rejects a class beyond the booking horizon (BR-7.2)', function () {
    $this->travelTo(Carbon::parse('2026-10-01 10:00'));
    $far = classSession(['starts_at' => '2026-10-20 05:00']);
    $near = classSession(['starts_at' => '2026-10-14 05:00']);

    expect(fn () => book($this, null, $far))->toThrow(BookingException::class, '14 days ahead');

    $this->travelTo(Carbon::parse('2026-10-07 10:00'));
    book($this, null, $far);
    expect(Booking::query()->count())->toBe(2);
});

it('treats the horizon as 14 calendar days counting today (BR-7.2)', function () {
    $this->travelTo(Carbon::parse('2026-10-05 12:00'));
    $last = classSession(['starts_at' => '2026-10-18 17:00']);
    $tooFar = classSession(['starts_at' => '2026-10-19 05:00']);

    book($this, [$this->household->members->first()->id], $last);
    expect(fn () => book($this, null, $tooFar))->toThrow(BookingException::class, '14 days ahead');
});

it('rejects a member the package does not cover (BR-4.10)', function () {
    $stranger = Member::factory()->for($this->household)->create();

    expect(fn () => book($this, [$stranger->id]))->toThrow(BookingException::class, "isn't covered");
    expect(Booking::query()->count())->toBe(0);
});

it('rejects an archived member (BR-2.5, 4.10)', function () {
    $member = $this->household->members->first();
    $member->forceFill(['archived_at' => now()])->save();

    expect(fn () => book($this, [$member->id]))->toThrow(BookingException::class);
});

it('rejects everyone when the membership is paused (BR-4.10)', function () {
    Membership::query()->update(['status' => 'paused']);

    expect(fn () => book($this))->toThrow(BookingException::class, 'active package');
});

it('rejects a double booking (BR-7.2, 7.5)', function () {
    $member = $this->household->members->first();
    book($this, [$member->id]);

    expect(fn () => book($this, [$member->id]))->toThrow(BookingException::class, 'already booked');
    expect(CreditLedger::balance($this->household))->toBe(7);
});

it('rejects a full session (BR-7.2)', function () {
    $this->session->update(['capacity' => 1]);
    GuestEntry::factory()->for($this->session)->create();

    expect(fn () => book($this))->toThrow(BookingException::class, 'full');
});

it('rejects when there are not enough credits (BR-7.2)', function () {
    CreditEntry::factory()->for($this->household)->ofType(CreditEntryType::Adjustment, -7)->create();

    expect(fn () => book($this))->toThrow(BookingException::class, 'You need 2 credits but have 1');
    expect(Booking::query()->count())->toBe(0);
});

it('books all or nothing: three members with two places left books nothing (BR-7.3)', function () {
    $user = bookingCustomer(3, 8);
    $this->session->update(['capacity' => 3]);
    GuestEntry::factory()->for($this->session)->create();

    expect(fn () => app(BookSession::class)->handle($user->household, $this->session, $user->household->members->pluck('id')->all(), $user))
        ->toThrow(BookingException::class, 'Only 2 places left');

    expect(Booking::query()->count())->toBe(0)
        ->and(CreditEntry::query()->where('type', CreditEntryType::Booking)->count())->toBe(0)
        ->and(CreditLedger::balance($user->household))->toBe(8);
});

it('counts guests and attended and no-show bookings as occupied (BR-7.4)', function () {
    $this->session->update(['capacity' => 5]);
    GuestEntry::factory()->for($this->session)->create(['participant_count' => 2]);
    Booking::factory()->for($this->session, 'classSession')->attended()->create();
    Booking::factory()->for($this->session, 'classSession')->noShow()->create();
    Booking::factory()->for($this->session, 'classSession')->cancelled()->create();

    $session = $this->session->fresh();

    expect($session->occupied())->toBe(4)
        ->and($session->placesLeft())->toBe(1);
});

it('lets a member rebook after cancelling because the active key frees up (BR-7.5)', function () {
    $member = $this->household->members->first();
    book($this, [$member->id]);
    Booking::query()->update(['status' => BookingStatus::Cancelled]);

    book($this, [$member->id]);

    expect(Booking::query()->where('member_id', $member->id)->count())->toBe(2)
        ->and(Booking::query()->where('status', BookingStatus::Booked)->count())->toBe(1);
});

it('rejects a second active booking for the same member with the unique index itself (BR-7.5)', function () {
    $booking = Booking::factory()->for($this->session, 'classSession')->create(['member_id' => $this->household->members->first()->id]);

    expect(fn () => Booking::factory()->for($this->session, 'classSession')->create(['member_id' => $booking->member_id]))
        ->toThrow(UniqueConstraintViolationException::class);
    expect(fn () => Booking::factory()->for($this->session, 'classSession')->attended()->create(['member_id' => $booking->member_id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('ignores another household\'s member id: nobody is booked and nothing is charged (BR-2.7)', function () {
    $other = bookingCustomer();
    $theirMember = $other->household->members->first();

    expect(fn () => book($this, [$theirMember->id]))->toThrow(BookingException::class);

    expect(Booking::query()->count())->toBe(0)
        ->and(CreditLedger::balance($other->household))->toBe(8)
        ->and(CreditLedger::balance($this->household))->toBe(8);
});

it('needs at least one person', function () {
    expect(fn () => book($this, []))->toThrow(BookingException::class);
});
