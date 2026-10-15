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
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('seeds an admin and three households in different membership states', function () {
    $this->seed(DemoSeeder::class);

    $smiths = User::query()->where('email', 'smith@optifit.test')->firstOrFail()->household;

    expect(User::query()->where('email', 'admin@optifit.test')->exists())->toBeTrue()
        ->and(Household::query()->count())->toBe(3)
        ->and(Membership::query()->where('status', 'requested')->count())->toBe(1)
        ->and(CreditLedger::balance($smiths))->toBe(25 - $smiths->bookings()->where('credit_charged', true)->count());
});

it('can be run twice without duplicating anything', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(Household::query()->count())->toBe(3);
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

    expect(ClassSession::query()->where('starts_at', '>', now())->where('starts_at', '<=', now()->addDays(14))->count())->toBeGreaterThan(8)
        ->and(Booking::query()->count())->toBeGreaterThan(8)
        ->and(CreditEntry::query()->where('type', CreditEntryType::Booking)->count())->toBe($charged)
        ->and(CreditEntry::query()->where('type', CreditEntryType::Booking)->whereNull('booking_id')->count())->toBe(0)
        ->and(GuestEntry::query()->count())->toBe(2)
        ->and($smiths->bookings()->where('status', BookingStatus::Attended)->count())->toBe(1)
        ->and($smiths->bookings()->where('status', BookingStatus::NoShow)->count())->toBe(1)
        ->and($smiths->bookings()->where('late_cancellation', true)->count())->toBe(1);
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
