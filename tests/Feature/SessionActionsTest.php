<?php

use App\Actions\Bookings\BookSession;
use App\Actions\Sessions\CancelSession;
use App\Actions\Sessions\GenerateSessions;
use App\Actions\Sessions\RescheduleSession;
use App\Enums\BookingStatus;
use App\Enums\CreditEntryType;
use App\Enums\SessionStatus;
use App\Exceptions\BookingException;
use App\Exceptions\SessionException;
use App\Mail\SessionCancelled;
use App\Mail\SessionTimeChanged;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditEntry;
use App\Models\GuestEntry;
use App\Models\Location;
use App\Models\ScheduleTemplate;
use App\Support\CreditLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $this->admin = admin();
});

// --- Generate (BR-8.2, 8.3) ---

it('creates one session per matching weekday and copies the template (BR-8.2)', function () {
    ScheduleTemplate::query()->delete();
    $template = ScheduleTemplate::factory()->on(1, '05:00:00')->create(['title' => 'Boot camp', 'capacity' => 12, 'min_participants' => 4, 'cutoff_hours' => 6, 'description' => 'Bring water']);

    $result = app(GenerateSessions::class)->handle(collect([$template]), Carbon::parse('2026-10-16'), Carbon::parse('2026-11-01'));

    expect($result)->toBe(['created' => 2, 'skipped' => 0]);
    $sessions = ClassSession::query()->orderBy('starts_at')->get();
    expect($sessions->pluck('starts_at')->map->format('Y-m-d H:i')->all())->toBe(['2026-10-19 05:00', '2026-10-26 05:00']);

    $first = $sessions->first();
    expect($first->title)->toBe('Boot camp')
        ->and($first->capacity)->toBe(12)
        ->and($first->min_participants)->toBe(4)
        ->and($first->cutoff_hours)->toBe(6)
        ->and($first->description)->toBe('Bring water')
        ->and($first->location_id)->toBe($template->location_id)
        ->and($first->schedule_template_id)->toBe($template->id)
        ->and($first->status)->toBe(SessionStatus::Scheduled);
});

it('skips dates where the template already has a session and reports the counts (BR-8.2)', function () {
    ScheduleTemplate::query()->delete();
    $template = ScheduleTemplate::factory()->on(1, '05:00:00')->create();
    $generate = fn () => app(GenerateSessions::class)->handle(collect([$template]), Carbon::parse('2026-10-16'), Carbon::parse('2026-11-01'));

    $generate();
    expect($generate())->toBe(['created' => 0, 'skipped' => 2])
        ->and(ClassSession::query()->count())->toBe(2);

    $result = app(GenerateSessions::class)->handle(collect([$template]), Carbon::parse('2026-10-16'), Carbon::parse('2026-11-08'));
    expect($result)->toBe(['created' => 1, 'skipped' => 2]);
});

it('counts the template\'s own sessions only, so two templates can share a slot (BR-8.2)', function () {
    ScheduleTemplate::query()->delete();
    $a = ScheduleTemplate::factory()->on(1, '05:00:00')->create();
    $b = ScheduleTemplate::factory()->on(1, '05:00:00')->create();

    $result = app(GenerateSessions::class)->handle(collect([$a, $b]), Carbon::parse('2026-10-19'), Carbon::parse('2026-10-19'));

    expect($result)->toBe(['created' => 2, 'skipped' => 0]);
});

it('does not change generated sessions when the template is edited (BR-8.3)', function () {
    ScheduleTemplate::query()->delete();
    $template = ScheduleTemplate::factory()->on(1, '05:00:00')->create(['capacity' => 15]);
    app(GenerateSessions::class)->handle(collect([$template]), Carbon::parse('2026-10-19'), Carbon::parse('2026-10-19'));

    $template->update(['capacity' => 8, 'title' => 'Renamed', 'start_time' => '06:00:00']);

    $session = ClassSession::query()->sole();
    expect($session->capacity)->toBe(15)
        ->and($session->title)->toBe('Morning Functional Fitness')
        ->and($session->starts_at->format('H:i'))->toBe('05:00');
});

// --- Cancel (BR-7.8) ---

function bookedSession(object $test, int $customers = 2): ClassSession
{
    $session = classSession(['starts_at' => '2026-10-19 05:00']);
    $test->customers = collect(range(1, $customers))->map(fn () => bookingCustomer());

    foreach ($test->customers as $user) {
        app(BookSession::class)->handle($user->household, $session, $user->household->members->pluck('id')->all(), $user);
    }
    Mail::fake();

    return $session;
}

it('cancels every booking, refunds the charged ones and emails each household once (BR-7.8)', function () {
    $session = bookedSession($this);
    $free = Booking::factory()->for($session, 'classSession')->free()->create();
    GuestEntry::factory()->for($session)->create();

    $households = app(CancelSession::class)->handle($session, ' Coach is ill ', $this->admin);

    $session->refresh();
    expect($households)->toBe(3)
        ->and($session->status)->toBe(SessionStatus::Cancelled)
        ->and($session->cancellation_reason)->toBe('Coach is ill')
        ->and(Booking::query()->where('status', BookingStatus::Booked)->count())->toBe(0)
        ->and(Booking::query()->where('status', BookingStatus::Cancelled)->where('cancelled_by', $this->admin->id)->count())->toBe(5)
        ->and($session->guestEntries()->count())->toBe(1);

    foreach ($this->customers as $user) {
        expect(CreditLedger::balance($user->household))->toBe(8);
    }
    expect(CreditEntry::query()->where('type', CreditEntryType::Refund)->count())->toBe(4)
        ->and(Booking::query()->find($free->id)->isRefunded())->toBeFalse();

    Mail::assertQueued(SessionCancelled::class, 3);
    Mail::assertQueued(SessionCancelled::class, fn (SessionCancelled $mail) => $mail->hasTo($this->customers[0]->email) && $mail->creditsReturned === 2);
});

it('cannot cancel a session twice or refund twice (BR-7.8, 5.7)', function () {
    $session = bookedSession($this, 1);
    app(CancelSession::class)->handle($session, null, $this->admin);

    expect(fn () => app(CancelSession::class)->handle($session, null, $this->admin))->toThrow(SessionException::class, 'already been cancelled');
    expect(CreditLedger::balance($this->customers[0]->household))->toBe(8);
    Mail::assertQueued(SessionCancelled::class, 1);
});

it('lets nobody book a cancelled session (BR-7.8, 7.2)', function () {
    $session = bookedSession($this, 1);
    app(CancelSession::class)->handle($session, null, $this->admin);
    $user = $this->customers[0];

    expect(fn () => app(BookSession::class)->handle($user->household, $session, [$user->household->members->first()->id], $user))
        ->toThrow(BookingException::class, 'cancelled');
});

// --- Reschedule (BR-7.10) ---

it('emails every booked household when the start time changes and leaves bookings alone (BR-7.10)', function () {
    $session = bookedSession($this);
    $bookings = Booking::query()->count();

    $emailed = app(RescheduleSession::class)->handle($session, ['starts_at' => '2026-10-19 06:00'], $this->admin);

    expect($emailed)->toBe(2)
        ->and($session->fresh()->starts_at->format('H:i'))->toBe('06:00')
        ->and(Booking::query()->where('status', BookingStatus::Booked)->count())->toBe($bookings);
    Mail::assertQueued(SessionTimeChanged::class, 2);
    Mail::assertQueued(SessionTimeChanged::class, fn (SessionTimeChanged $mail) => $mail->previousStartsAt->format('H:i') === '05:00' && $mail->session->starts_at->format('H:i') === '06:00');
});

it('sends no time-change email when only other fields change, and capacity edits leave bookings alone (BR-7.10)', function () {
    $session = bookedSession($this);

    $emailed = app(RescheduleSession::class)->handle($session, ['capacity' => 1, 'title' => 'Renamed', 'starts_at' => '2026-10-19 05:00'], $this->admin);

    expect($emailed)->toBe(0)
        ->and($session->fresh()->capacity)->toBe(1)
        ->and(Booking::query()->where('status', BookingStatus::Booked)->count())->toBe(4);
    Mail::assertNothingQueued();
});

it('does not email households whose booking was cancelled when the time changes (BR-7.10)', function () {
    $session = bookedSession($this);
    Booking::query()->where('household_id', $this->customers[0]->household->id)->update(['status' => BookingStatus::Cancelled]);

    expect(app(RescheduleSession::class)->handle($session, ['starts_at' => '2026-10-19 06:00'], $this->admin))->toBe(1);
});

// --- Guests (BR-9.3) ---

it('lets guests take places without costing credits (BR-9.3)', function () {
    $session = classSession(['capacity' => 5]);
    $user = bookingCustomer();

    GuestEntry::factory()->for($session)->create(['participant_count' => 3]);

    expect($session->fresh()->placesLeft())->toBe(2)
        ->and(CreditLedger::balance($user->household))->toBe(8);

    $session->guestEntries()->delete();
    expect($session->fresh()->placesLeft())->toBe(5);
});

// --- Locations (BR-9.2) ---

it('keeps a location in use out of the archive (BR-9.2)', function () {
    $location = Location::query()->sole();

    expect(fn () => $location->archive())->toThrow(SessionException::class, 'still used');
});
