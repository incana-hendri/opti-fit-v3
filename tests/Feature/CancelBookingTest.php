<?php

use App\Actions\Bookings\AdminBook;
use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\MarkAttendance;
use App\Actions\Credits\ReturnCredit;
use App\Enums\BookingStatus;
use App\Enums\CreditEntryType;
use App\Exceptions\BookingException;
use App\Exceptions\CreditException;
use App\Mail\BookingCancelled;
use App\Mail\BookingCancelledByStudio;
use App\Models\Booking;
use App\Models\CreditEntry;
use App\Models\GuestEntry;
use App\Models\Member;
use App\Support\CreditLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

// Friday 16 Oct 2026. The Monday 19 Oct 05:00 class has its cutoff on Sunday 18 Oct at 21:00.
beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $this->user = bookingCustomer();
    $this->household = $this->user->household;
    $this->admin = admin();
    $this->session = classSession(['starts_at' => '2026-10-19 05:00']);
    $this->mia = $this->household->members[0];
    $this->liam = $this->household->members[1];

    $this->bookings = app(BookSession::class)->handle($this->household, $this->session, [$this->mia->id, $this->liam->id], $this->user);
    Mail::fake();
});

function cancel(object $test, int $index = 0, ?object $by = null, ?bool $returnCredit = null): bool
{
    return app(CancelBooking::class)->handle($test->bookings[$index]->fresh(), $by ?? $test->user, $returnCredit);
}

// --- Customer (BR-7.7, 7.11) ---

it('refunds the credit when cancelled before the cutoff (BR-7.7)', function () {
    expect(cancel($this))->toBeTrue();

    $booking = $this->bookings[0]->fresh();
    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->late_cancellation)->toBeFalse()
        ->and($booking->cancelled_at)->not->toBeNull()
        ->and($booking->cancelled_by)->toBe($this->user->id)
        ->and(CreditLedger::balance($this->household))->toBe(7);

    $refund = CreditEntry::query()->where('type', CreditEntryType::Refund)->sole();
    expect($refund->amount)->toBe(1)
        ->and($refund->booking_id)->toBe($booking->id)
        ->and($refund->idempotency_key)->toBe("refund:{$booking->id}");
});

it('records a late cancellation without a refund after the cutoff (BR-7.7)', function () {
    $this->travelTo(Carbon::parse('2026-10-18 21:00'));

    expect(cancel($this))->toBeFalse();

    $booking = $this->bookings[0]->fresh();
    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->late_cancellation)->toBeTrue()
        ->and(CreditLedger::balance($this->household))->toBe(6)
        ->and(CreditEntry::query()->where('type', CreditEntryType::Refund)->count())->toBe(0);
});

it('rejects a customer cancelling after the class has started (BR-7.7, 7.11)', function () {
    $this->travelTo(Carbon::parse('2026-10-19 05:00'));

    expect(fn () => cancel($this))->toThrow(BookingException::class, 'already started');
    expect($this->bookings[0]->fresh()->status)->toBe(BookingStatus::Booked);
});

it('rejects cancelling twice', function () {
    cancel($this);

    expect(fn () => cancel($this))->toThrow(BookingException::class);
    expect(CreditLedger::balance($this->household))->toBe(7);
});

it('queues the cancellation email saying whether the credit came back (C9)', function () {
    cancel($this);

    Mail::assertQueued(BookingCancelled::class, fn (BookingCancelled $mail) => $mail->hasTo($this->user->email) && $mail->creditReturned);
});

it('does not refund a free booking when a customer cancels it (BR-6.3)', function () {
    $free = app(AdminBook::class)->handle($this->household, classSession(['starts_at' => '2026-10-20 05:00']), [$this->mia->id], false, false, $this->admin)->first();

    expect(app(CancelBooking::class)->handle($free, $this->user))->toBeFalse();
    expect(CreditLedger::balance($this->household))->toBe(6);
});

it('refuses another household\'s customer the cancellation (BR-2.7)', function () {
    $stranger = bookingCustomer();

    expect(fn () => cancel($this, 0, $stranger))->toThrow(BookingException::class);
    expect($this->bookings[0]->fresh()->status)->toBe(BookingStatus::Booked);
});

// --- Admin (BR-6.1, 6.2) ---

it('defaults to returning the credit when an admin cancels before the cutoff (BR-6.2)', function () {
    expect(cancel($this, 0, $this->admin))->toBeTrue();

    expect(CreditLedger::balance($this->household))->toBe(7)
        ->and($this->bookings[0]->fresh()->late_cancellation)->toBeFalse();
    Mail::assertQueued(BookingCancelledByStudio::class, fn ($mail) => $mail->hasTo($this->user->email) && $mail->creditReturned);
});

it('defaults to keeping the credit when an admin cancels after the cutoff (BR-6.2)', function () {
    $this->travelTo(Carbon::parse('2026-10-18 22:00'));

    expect(cancel($this, 0, $this->admin))->toBeFalse();
    expect(CreditLedger::balance($this->household))->toBe(6);
});

it('lets the admin override the return-credit default either way (BR-6.2)', function () {
    expect(cancel($this, 0, $this->admin, false))->toBeFalse();
    expect(CreditLedger::balance($this->household))->toBe(6);

    $this->travelTo(Carbon::parse('2026-10-18 22:00'));
    expect(cancel($this, 1, $this->admin, true))->toBeTrue();
    expect(CreditLedger::balance($this->household))->toBe(7);
});

it('lets an admin cancel after the class has started (BR-6.4)', function () {
    $this->travelTo(Carbon::parse('2026-10-19 05:30'));

    cancel($this, 0, $this->admin, true);

    expect($this->bookings[0]->fresh()->status)->toBe(BookingStatus::Cancelled);
});

it('never refunds a free booking, even when the admin asks (BR-6.3)', function () {
    $free = app(AdminBook::class)->handle($this->household, classSession(['starts_at' => '2026-10-20 05:00']), [$this->mia->id], false, false, $this->admin)->first();

    expect(app(CancelBooking::class)->handle($free, $this->admin, true))->toBeFalse();
    expect(CreditLedger::balance($this->household))->toBe(6);
});

// --- Return credit (BR-6.1, 5.7) ---

it('returns the credit of a late cancellation once (BR-6.1, 5.7)', function () {
    $this->travelTo(Carbon::parse('2026-10-18 22:00'));
    cancel($this);
    $booking = $this->bookings[0]->fresh();

    expect($booking->canReturnCredit())->toBeTrue();
    app(ReturnCredit::class)->handle($booking, $this->admin);

    expect(CreditLedger::balance($this->household))->toBe(7)
        ->and($booking->fresh()->canReturnCredit())->toBeFalse();
    expect(fn () => app(ReturnCredit::class)->handle($booking, $this->admin))->toThrow(CreditException::class, 'already been refunded');
    expect(CreditLedger::balance($this->household))->toBe(7);
});

it('returns the credit of a no-show and of an attended booking (BR-6.1)', function () {
    $this->travelTo(Carbon::parse('2026-10-19 05:00'));
    app(MarkAttendance::class)->handle($this->bookings[0]->fresh(), BookingStatus::NoShow);
    app(MarkAttendance::class)->handle($this->bookings[1]->fresh(), BookingStatus::Attended);

    app(ReturnCredit::class)->handle($this->bookings[0]->fresh(), $this->admin);
    app(ReturnCredit::class)->handle($this->bookings[1]->fresh(), $this->admin);

    expect(CreditLedger::balance($this->household))->toBe(8);
});

it('does not double refund a booking the customer already got back (BR-5.7)', function () {
    cancel($this);

    expect(fn () => app(ReturnCredit::class)->handle($this->bookings[0]->fresh(), $this->admin))->toThrow(CreditException::class);
    expect(CreditLedger::balance($this->household))->toBe(7);
});

it('does not return a credit for a booking that is still booked or was free (BR-6.1)', function () {
    expect(fn () => app(ReturnCredit::class)->handle($this->bookings[0]->fresh(), $this->admin))->toThrow(CreditException::class, 'Cancel the booking first');

    $free = Booking::factory()->for($this->session, 'classSession')->free()->noShow()->create();
    expect(fn () => app(ReturnCredit::class)->handle($free, $this->admin))->toThrow(CreditException::class, 'free');
});

it('flags a late cancellation by the clock, for the admin as for the customer (BR-7.7)', function () {
    cancel($this, 0, $this->admin, false);

    expect($this->bookings[0]->fresh()->late_cancellation)->toBeFalse();

    $this->travelTo(Carbon::parse('2026-10-18 22:00'));
    cancel($this, 1, $this->admin, true);

    expect($this->bookings[1]->fresh()->late_cancellation)->toBeTrue();
});

it('lets an admin undo a cancellation that kept the credit (BR-6.1, 6.2)', function () {
    cancel($this, 0, $this->admin, false);
    $booking = $this->bookings[0]->fresh();

    expect($booking->late_cancellation)->toBeFalse()->and($booking->canReturnCredit())->toBeTrue();
    app(ReturnCredit::class)->handle($booking, $this->admin);

    expect(CreditLedger::balance($this->household))->toBe(7);
});

// --- Attendance (BR-7.9) ---

it('cannot mark attendance earlier than 30 minutes before the start (BR-7.9)', function () {
    $this->travelTo(Carbon::parse('2026-10-19 04:29'));
    expect(fn () => app(MarkAttendance::class)->handle($this->bookings[0]->fresh(), BookingStatus::Attended))->toThrow(BookingException::class, '30 minutes');

    $this->travelTo(Carbon::parse('2026-10-19 04:30'));
    app(MarkAttendance::class)->handle($this->bookings[0]->fresh(), BookingStatus::Attended);

    expect($this->bookings[0]->fresh()->status)->toBe(BookingStatus::Attended);
});

it('leaves the credits alone when attendance is marked (BR-7.9)', function () {
    $this->travelTo(Carbon::parse('2026-10-19 05:30'));
    $ledgerRows = CreditEntry::query()->count();

    app(MarkAttendance::class)->handle($this->bookings[0]->fresh(), BookingStatus::NoShow);
    app(MarkAttendance::class)->handle($this->bookings[0]->fresh(), BookingStatus::Attended);

    expect(CreditEntry::query()->count())->toBe($ledgerRows)
        ->and($this->bookings[0]->fresh()->status)->toBe(BookingStatus::Attended);
});

it('only marks attended or no-show, and not a cancelled booking (BR-7.9)', function () {
    $this->travelTo(Carbon::parse('2026-10-19 05:30'));
    cancel($this, 0, $this->admin);

    expect(fn () => app(MarkAttendance::class)->handle($this->bookings[1]->fresh(), BookingStatus::Cancelled))->toThrow(BookingException::class)
        ->and(fn () => app(MarkAttendance::class)->handle($this->bookings[0]->fresh(), BookingStatus::Attended))->toThrow(BookingException::class);
});

// --- Admin booking (BR-6.3, 6.4) ---

it('books over capacity only when the admin says so (BR-6.4)', function () {
    $session = classSession(['starts_at' => '2026-10-20 05:00', 'capacity' => 1]);
    GuestEntry::factory()->for($session)->create();

    expect(fn () => app(AdminBook::class)->handle($this->household, $session, [$this->mia->id], true, false, $this->admin))->toThrow(BookingException::class, 'full');

    app(AdminBook::class)->handle($this->household, $session, [$this->mia->id], true, true, $this->admin);

    expect($session->fresh()->placesLeft())->toBe(-1)
        ->and($session->fresh()->capacity)->toBe(1);
});

it('writes no ledger row for a free admin booking (BR-6.3)', function () {
    $rows = CreditEntry::query()->count();

    $booking = app(AdminBook::class)->handle($this->household, classSession(['starts_at' => '2026-10-20 05:00']), [$this->mia->id], false, false, $this->admin)->first();

    expect($booking->credit_charged)->toBeFalse()
        ->and(CreditEntry::query()->count())->toBe($rows)
        ->and(CreditLedger::balance($this->household))->toBe(6);
});

it('still needs enough credits for a charged admin booking (BR-6.3)', function () {
    CreditEntry::factory()->for($this->household)->ofType(CreditEntryType::Adjustment, -6)->create();

    expect(fn () => app(AdminBook::class)->handle($this->household, classSession(['starts_at' => '2026-10-20 05:00']), [$this->mia->id], true, false, $this->admin))
        ->toThrow(BookingException::class, '0 credits');
});

it('ignores the cutoff, the month and the covered-members rule for an admin booking (BR-6.4)', function () {
    $this->travelTo(Carbon::parse('2026-10-18 22:00'));
    $closed = $this->session;
    $nextMonth = classSession(['starts_at' => '2026-11-03 05:00']);
    $outsider = Member::factory()->for($this->household)->create();

    app(AdminBook::class)->handle($this->household, $nextMonth, [$outsider->id], true, false, $this->admin);

    expect(Booking::query()->where('member_id', $outsider->id)->sole()->booked_by)->toBe($this->admin->id);
});

it('refuses an admin booking for an archived member or one from another household', function () {
    $other = bookingCustomer()->household->members->first();
    $archived = Member::factory()->for($this->household)->archived()->create();
    $session = classSession(['starts_at' => '2026-10-20 05:00']);

    expect(fn () => app(AdminBook::class)->handle($this->household, $session, [$other->id], true, false, $this->admin))->toThrow(BookingException::class)
        ->and(fn () => app(AdminBook::class)->handle($this->household, $session, [$archived->id], true, false, $this->admin))->toThrow(BookingException::class);
});

it('sends no booking confirmation for an admin booking on a class that has already started (C8)', function () {
    $this->travelTo(Carbon::parse('2026-10-19 05:30'));
    Mail::fake();

    $started = classSession(['starts_at' => '2026-10-19 04:00']);

    app(AdminBook::class)->handle($this->household, $started, [$this->mia->id], false, false, $this->admin);

    Mail::assertNothingQueued();
    expect($started->bookings()->where('status', BookingStatus::Booked)->count())->toBe(1);
});
