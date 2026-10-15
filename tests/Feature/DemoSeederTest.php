<?php

use App\Enums\BookingStatus;
use App\Enums\CreditEntryType;
use App\Livewire\BookPage;
use App\Mail\AdminBelowMinimum;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditEntry;
use App\Models\GuestEntry;
use App\Models\Household;
use App\Models\Membership;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\MonthReport;
use App\Support\Period;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('seeds two admins and four households in different states', function () {
    $this->seed(DemoSeeder::class);

    $smiths = User::query()->where('email', 'smith@optifit.test')->firstOrFail()->household;
    $thisMonth = $smiths->bookings()->where('credit_charged', true)
        ->whereHas('classSession', fn ($session) => $session->where('starts_at', '>=', Period::firstDay(Period::current())))
        ->count();

    expect(User::query()->where('role', 'admin')->pluck('email')->all())->toBe(['admin@optifit.test', 'owner@optifit.test'])
        ->and(Household::query()->count())->toBe(4)
        ->and(Membership::query()->where('status', 'requested')->count())->toBe(1)
        ->and(Household::query()->deletionRequested()->count())->toBe(1)
        ->and(CreditLedger::balance($smiths))->toBe(25 - $thisMonth);
});

it('can be run twice without duplicating anything', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Household::query()->count())->toBe(4);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class);
});

it('seeds two weeks of sessions and bookings whose ledger rows add up (M3)', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00'));
    $this->seed(DemoSeeder::class);

    $smiths = User::query()->where('email', 'smith@optifit.test')->firstOrFail()->household;
    $charged = Booking::query()->where('credit_charged', true)->count();
    $thisMonth = fn (BookingStatus $status) => $smiths->bookings()->where('status', $status)
        ->whereHas('classSession', fn ($session) => $session->where('starts_at', '>=', Period::firstDay(Period::current())))->count();

    expect(ClassSession::query()->where('starts_at', '>', now())->where('starts_at', '<=', now()->addDays(14))->count())->toBeGreaterThan(8)
        ->and(Booking::query()->count())->toBeGreaterThan(8)
        ->and(CreditEntry::query()->where('type', CreditEntryType::Booking)->count())->toBe($charged)
        ->and(CreditEntry::query()->where('type', CreditEntryType::Booking)->whereNull('booking_id')->count())->toBe(0)
        ->and(GuestEntry::query()->count())->toBe(2)
        ->and($thisMonth(BookingStatus::Attended))->toBe(1)
        ->and($thisMonth(BookingStatus::NoShow))->toBe(1)
        ->and($smiths->bookings()->where('late_cancellation', true)->whereHas('classSession', fn ($session) => $session->where('starts_at', '>=', Period::firstDay(Period::current())))->count())->toBe(1);
});

it('leaves a class today below its minimum so the A5 email has something to say (M3)', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00'));
    $this->seed(DemoSeeder::class);
    Mail::fake();

    $this->artisan('optifit:below-minimum')->assertSuccessful();

    Mail::assertQueued(AdminBelowMinimum::class, fn (AdminBelowMinimum $mail) => $mail->sessions->pluck('title')->contains('Evening Functional Fitness'));
});

it('seeds a bookable, a full and an all-booked class for the screens (M3)', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00'));
    $this->seed(DemoSeeder::class);
    $user = User::query()->where('email', 'smith@optifit.test')->firstOrFail();

    $states = Livewire::actingAs($user)->test(BookPage::class)->viewData('states')->unique()->sort()->values()->all();

    expect($states)->toBe(['all-booked', 'bookable', 'closed', 'full']);
});

it('gives the dashboard and Reports something real to show (M4)', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00'));
    $this->seed(DemoSeeder::class);

    $october = new MonthReport('2026-10');
    $september = new MonthReport('2026-09');

    expect($october->totals()['attendanceRate'])->toBeGreaterThan(0)->toBeLessThan(100)
        ->and($october->totals()['attended'])->toBeGreaterThan(0)
        ->and($october->totals()['noShows'])->toBeGreaterThan(0)
        ->and($october->totals()['lateCancellations'])->toBe(1)
        ->and($october->totals()['guestPlaces'])->toBeGreaterThan(0)
        ->and($october->activeMemberships())->toBe(2)
        ->and($september->totals()['bookings'])->toBeGreaterThan(8)
        ->and($september->totals()['noShows'])->toBe(1)
        ->and($september->credits()['granted'])->toBe(24)
        ->and($september->credits()['expired'])->toBeGreaterThan(0)
        ->and(count($september->sessions()))->toBeGreaterThan(0);
});

it('leaves last month with a balance of zero once its credits expired (M4)', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00'));
    $this->seed(DemoSeeder::class);

    $smiths = User::query()->where('email', 'smith@optifit.test')->firstOrFail()->household;

    expect(CreditLedger::balance($smiths, '2026-09'))->toBe(0)
        ->and(CreditEntry::query()->where('household_id', $smiths->id)->where('period', '2026-09')->where('type', CreditEntryType::Booking)->count())->toBeGreaterThan(8);
});
