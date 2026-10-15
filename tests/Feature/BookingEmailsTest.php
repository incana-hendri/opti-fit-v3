<?php

use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Sessions\CancelSession;
use App\Mail\AdminBelowMinimum;
use App\Mail\BookingCancelled;
use App\Mail\BookingCancelledByStudio;
use App\Mail\BookingConfirmed;
use App\Mail\SessionCancelled;
use App\Mail\SessionTimeChanged;
use App\Models\Booking;
use App\Models\GuestEntry;
use App\Models\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $this->user = bookingCustomer();
    $this->session = classSession(['starts_at' => '2026-10-19 05:00', 'title' => 'Morning Functional Fitness']);
    $this->bookings = app(BookSession::class)->handle($this->user->household, $this->session, $this->user->household->members->pluck('id')->all(), $this->user);
});

it('renders C8 with the members, venue, free-cancellation time and credits left', function () {
    $mail = new BookingConfirmed($this->session, $this->bookings, 6);

    expect($mail->envelope()->subject)->toBe('Booked: Morning Functional Fitness, Mon 19 Oct, 05:00')
        ->and($mail)->toBeInstanceOf(ShouldQueue::class);

    $html = $mail->render();
    expect($html)
        ->toContain($this->bookings[0]->member->first_name)
        ->toContain($this->session->location->name)
        ->toContain('Free cancellation until')
        ->toContain('Sun 18 Oct, 21:00')
        ->toContain('6 credits')
        ->toContain(route('bookings'));
});

it('says the free-cancellation time has passed when an admin books after the cutoff (C8)', function () {
    $this->travelTo(Carbon::parse('2026-10-18 22:00'));

    expect((new BookingConfirmed($this->session, $this->bookings, 6))->render())->toContain('has already passed')->not->toContain('Free cancellation until');
});

it('renders C9 and C10 with the right wording about the credit', function () {
    $booking = $this->bookings[0]->load(['household.user', 'member', 'classSession']);

    $returned = new BookingCancelled($booking, true);
    $kept = new BookingCancelled($booking, false);
    $byStudio = new BookingCancelledByStudio($booking, true);

    expect($returned->envelope()->subject)->toBe('Cancelled: Morning Functional Fitness, Mon 19 Oct, 05:00')
        ->and($returned->render())->toContain('Your credit has been returned')
        ->and($kept->render())->toContain('late cancellation')->not->toContain('Your credit has been returned')
        ->and($byStudio->envelope()->subject)->toBe('Your booking was cancelled')
        ->and($byStudio->render())->toContain('the studio cancelled')->toContain('Your credit has been returned');
});

it('renders C11 with the reason, credits returned and a Book another class button', function () {
    $this->session->update(['cancellation_reason' => 'The coach is ill.']);
    $mail = new SessionCancelled($this->session, 2);

    expect($mail->envelope()->subject)->toBe('Class cancelled: Morning Functional Fitness, Mon 19 Oct, 05:00')
        ->and($mail->render())->toContain('The coach is ill.')->toContain('2 credits have been returned')->toContain('Book another class')->toContain(route('book'));
});

it('renders C12 with the old and new time and the new free-cancellation time', function () {
    $this->session->update(['starts_at' => '2026-10-19 06:00']);
    $mail = new SessionTimeChanged($this->session, Carbon::parse('2026-10-19 05:00'));

    expect($mail->envelope()->subject)->toBe('Class time changed: Morning Functional Fitness')
        ->and($mail->render())->toContain('Mon 19 Oct, 05:00')->toContain('Mon 19 Oct, 06:00')->toContain('Cancel for free until')->toContain('Sun 18 Oct, 22:00');
});

it('queues C9 to the customer and C10 to the customer when the admin cancels (C9, C10)', function () {
    Mail::fake();

    app(CancelBooking::class)->handle($this->bookings[0]->fresh(), $this->user);
    app(CancelBooking::class)->handle($this->bookings[1]->fresh(), admin());

    Mail::assertQueued(BookingCancelled::class, 1);
    Mail::assertQueued(BookingCancelledByStudio::class, 1);
});

it('queues C11 to each household once (C11)', function () {
    Mail::fake();

    app(CancelSession::class)->handle($this->session, 'Storm', admin());

    Mail::assertQueued(SessionCancelled::class, fn (SessionCancelled $mail) => $mail->hasTo($this->user->email));
    Mail::assertQueued(SessionCancelled::class, 1);
});

// --- A5 ---

it('emails the admins only the classes below their minimum today (A5)', function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-19 04:00'));
    $this->session->update(['min_participants' => 3, 'title' => 'Too quiet']);
    $full = classSession(['starts_at' => '2026-10-19 16:00', 'min_participants' => 1, 'title' => 'Busy']);
    Booking::factory()->for($full, 'classSession')->create();
    classSession(['starts_at' => '2026-10-19 17:00', 'min_participants' => null, 'title' => 'No minimum']);
    classSession(['starts_at' => '2026-10-20 05:00', 'min_participants' => 5, 'title' => 'Tomorrow']);
    classSession(['starts_at' => '2026-10-19 18:00', 'min_participants' => 5, 'title' => 'Cancelled one'])->update(['status' => 'cancelled']);

    $this->artisan('optifit:below-minimum')->assertSuccessful();

    Mail::assertQueued(AdminBelowMinimum::class, 1);
    Mail::assertQueued(AdminBelowMinimum::class, function (AdminBelowMinimum $mail) {
        $html = $mail->render();

        return $mail->hasTo('info@opti-fit.co.za')
            && $mail->envelope()->subject === 'Classes below minimum today'
            && $mail->sessions->pluck('title')->all() === ['Too quiet']
            && str_contains($html, '2 of 3 minimum');
    });
});

it('counts guests towards the minimum and sends nothing when every class is fine (A5)', function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-19 04:00'));
    GuestEntry::factory()->for($this->session)->create();

    $this->artisan('optifit:below-minimum')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('sends A5 to every admin address and nothing when none is set (A5)', function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-19 04:00'));
    $this->session->update(['min_participants' => 9]);
    Settings::query()->first()->update(['admin_notification_emails' => 'a@example.com, b@example.com']);

    $this->artisan('optifit:below-minimum')->assertSuccessful();
    Mail::assertQueued(AdminBelowMinimum::class, fn (AdminBelowMinimum $mail) => $mail->hasTo('a@example.com') && $mail->hasTo('b@example.com'));

    Mail::fake();
    Settings::query()->first()->update(['admin_notification_emails' => null]);
    $this->artisan('optifit:below-minimum')->assertSuccessful();
    Mail::assertNothingQueued();
});

it('schedules the below-minimum check at 06:00 (docs/07)', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'optifit:below-minimum'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 6 * * *');
});
