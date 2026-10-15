<?php

use App\Enums\CreditEntryType;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditEntry;
use App\Models\GuestEntry;
use App\Models\Household;
use App\Models\Membership;
use App\Models\Package;
use App\Support\MonthReport;
use Illuminate\Support\Carbon;

// docs/05 § Dashboard and Reports: one class computes the month's numbers for both.
beforeEach(function () {
    // A Tuesday midday, so "24 hours ago" falls on the 19th.
    $this->travelTo(Carbon::parse('2026-10-20 12:00'));
});

function reportSession(string $startsAt, int $capacity = 10, bool $cancelled = false): ClassSession
{
    return ClassSession::factory()->at($startsAt)->state(['capacity' => $capacity])
        ->when($cancelled, fn ($factory) => $factory->cancelled())->create();
}

/**
 * @param  'booked'|'attended'|'noShow'|'cancelled'|'lateCancelled'  $kind
 */
function reportBookings(ClassSession $session, string $kind, int $count = 1): void
{
    $factory = Booking::factory()->count($count)->for($session, 'classSession');
    $factory = match ($kind) {
        'attended' => $factory->attended(),
        'noShow' => $factory->noShow(),
        'cancelled' => $factory->cancelled(),
        'lateCancelled' => $factory->cancelled(late: true),
        default => $factory,
    };
    $factory->create();
}

function busyOctober(): void
{
    // Started more than 24 hours ago.
    $settled = reportSession('2026-10-05 05:00');
    reportBookings($settled, 'attended', 2);
    reportBookings($settled, 'noShow');
    reportBookings($settled, 'booked');
    reportBookings($settled, 'cancelled');
    reportBookings($settled, 'lateCancelled');
    GuestEntry::factory()->for($settled)->create(['participant_count' => 2]);

    // Started 19 hours ago: a booking still `booked` is not attended yet.
    $recent = reportSession('2026-10-19 17:00');
    reportBookings($recent, 'booked');
    reportBookings($recent, 'attended');

    $upcoming = reportSession('2026-10-25 05:00');
    reportBookings($upcoming, 'booked', 2);
    GuestEntry::factory()->for($upcoming)->create();

    // Cancelled by the studio: its bookings are cancellations and its guests don't count.
    $called = reportSession('2026-10-08 05:00', cancelled: true);
    reportBookings($called, 'cancelled', 2);
    GuestEntry::factory()->for($called)->create(['participant_count' => 3]);

    // Other months.
    reportBookings(reportSession('2026-09-30 17:00'), 'attended', 4);
    reportBookings(reportSession('2026-11-02 05:00'), 'booked', 4);
}

it('counts the month\'s bookings, attendance and cancellations (docs/05 § Reports)', function () {
    busyOctober();

    expect((new MonthReport('2026-10'))->totals())->toBe([
        'bookings' => 8,
        'attended' => 4,
        'noShows' => 1,
        'lateCancellations' => 1,
        'cancellations' => 3,
        'guestPlaces' => 3,
        'attendanceRate' => 80,
    ]);
});

it('counts a booking still booked 24 hours after the start as attended, and not before (BR-7.9)', function () {
    $this->travelTo(Carbon::parse('2026-10-20 12:00'));
    reportBookings(reportSession('2026-10-19 12:00'), 'booked');
    reportBookings(reportSession('2026-10-19 12:01'), 'booked');

    expect((new MonthReport('2026-10'))->totals())->toMatchArray(['bookings' => 2, 'attended' => 1]);
});

it('leaves the attendance rate empty when nothing has been held', function () {
    reportBookings(reportSession('2026-10-25 05:00'), 'booked');

    expect((new MonthReport('2026-10'))->totals())->toMatchArray(['attended' => 0, 'noShows' => 0, 'attendanceRate' => null]);
});

it('lists the scheduled sessions with occupancy, fill and attended (BR-7.4, 7.9)', function () {
    busyOctober();

    $sessions = (new MonthReport('2026-10'))->sessions();

    expect($sessions)->toHaveCount(3)
        ->and(array_column($sessions, 'startsAt'))->each->toBeInstanceOf(Carbon::class)
        ->and($sessions[0])->toMatchArray(['capacity' => 10, 'occupied' => 6, 'fillPercent' => 60, 'attended' => 3])
        ->and($sessions[1])->toMatchArray(['occupied' => 2, 'fillPercent' => 20, 'attended' => 1])
        ->and($sessions[2])->toMatchArray(['occupied' => 3, 'fillPercent' => 30, 'attended' => 0]);
});

it('sums the ledger by the entry period (BR-5.1, 5.2)', function () {
    $household = Household::factory()->create();
    $entries = [
        ['2026-10', CreditEntryType::Grant, 8],
        ['2026-10', CreditEntryType::Grant, 4],
        ['2026-10', CreditEntryType::Booking, -1],
        ['2026-10', CreditEntryType::Booking, -1],
        ['2026-10', CreditEntryType::Booking, -1],
        ['2026-10', CreditEntryType::Booking, -1],
        ['2026-10', CreditEntryType::Booking, -1],
        ['2026-10', CreditEntryType::Refund, 2],
        ['2026-10', CreditEntryType::Adjustment, 3],
        ['2026-10', CreditEntryType::Adjustment, -1],
        ['2026-09', CreditEntryType::Grant, 8],
        ['2026-09', CreditEntryType::Expiry, -3],
    ];
    foreach ($entries as [$period, $type, $amount]) {
        CreditEntry::factory()->for($household)->forPeriod($period)->ofType($type, $amount)->create();
    }

    expect((new MonthReport('2026-10'))->credits())->toBe(['granted' => 12, 'used' => 3, 'adjusted' => 2, 'expired' => 0])
        ->and((new MonthReport('2026-09'))->credits())->toBe(['granted' => 8, 'used' => 0, 'adjusted' => 0, 'expired' => 3]);
});

it('counts the memberships active at month end per package, with income', function () {
    $once = Package::factory()->create(['name' => 'Once a week', 'sort_order' => 1]);
    $twice = Package::factory()->create(['name' => 'Twice a week', 'sort_order' => 2]);
    $make = fn (Package $package, int $cents) => Membership::factory()->for($package)->state(['monthly_price_cents' => $cents]);

    $make($twice, 78000)->active('2026-08')->create();
    $make($once, 28000)->active('2026-09')->create();
    $make($once, 28000)->ending()->state(['starts_period' => '2026-10'])->create();
    $make($twice, 52000)->paused()->create();
    $make($once, 28000)->create();
    $make($once, 28000)->active('2026-11')->create();
    // Cancelled now, in October: not active at the end of the month.
    $make($once, 28000)->active('2026-09')->state(['status' => 'cancelled', 'ends_period' => '2026-10'])->create();
    // Cancelled after September: it was active at the end of September.
    $make($twice, 52000)->cancelled()->state(['starts_period' => '2026-08', 'ends_period' => '2026-09'])->create();

    $october = new MonthReport('2026-10');

    expect($october->memberships())->toBe([
        ['package' => 'Once a week', 'count' => 2, 'income' => 56000],
        ['package' => 'Twice a week', 'count' => 1, 'income' => 78000],
    ])
        ->and($october->activeMemberships())->toBe(3)
        ->and($october->monthlyIncome())->toBe(134000)
        ->and((new MonthReport('2026-09'))->memberships())->toBe([
            ['package' => 'Once a week', 'count' => 2, 'income' => 56000],
            ['package' => 'Twice a week', 'count' => 2, 'income' => 130000],
        ]);
});
