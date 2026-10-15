<?php

use App\Filament\Pages\Reports;
use App\Models\Booking;
use App\Models\Membership;
use App\Models\Package;
use App\Support\MonthReport;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// docs/05 § Reports
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-20 12:00'));
});

it('renders for an admin and is forbidden for a customer (BR-1.7)', function () {
    $this->actingAs(customer())->get('/admin/reports')->assertForbidden();
    $this->actingAs(admin())->get('/admin/reports')->assertOk()->assertSee('Totals')->assertSee('Export CSV');
});

it('offers the current month and the twelve before it, defaulting to the current one', function () {
    $this->actingAs(admin());

    $page = Livewire::test(Reports::class)->assertSet('month', '2026-10');
    $periods = $page->instance()->periods();

    expect(array_keys($periods))->toHaveCount(13)->and(array_key_first($periods))->toBe('2026-10')->and(array_key_last($periods))->toBe('2025-10')
        ->and($periods['2026-10'])->toBe('October 2026');
});

it('keeps the month in the query string and ignores one it does not offer', function () {
    $this->actingAs(admin());

    Livewire::withQueryParams(['month' => '2026-08'])->test(Reports::class)->assertSet('month', '2026-08');
    Livewire::withQueryParams(['month' => '1999-01'])->test(Reports::class)->assertSet('month', '2026-10');
    Livewire::withQueryParams(['month' => 'nonsense'])->test(Reports::class)->assertSet('month', '2026-10');
});

it('shows the same numbers as MonthReport', function () {
    $this->actingAs(admin());
    $session = classSession(['title' => 'Monday Madness', 'starts_at' => '2026-10-05 05:00', 'capacity' => 10]);
    Booking::factory()->count(2)->for($session, 'classSession')->attended()->create();
    Booking::factory()->for($session, 'classSession')->noShow()->create();
    Membership::factory()->for(Package::factory()->create(['name' => 'Gold']))->active()->state(['monthly_price_cents' => 52000])->create();

    $totals = (new MonthReport('2026-10'))->totals();

    Livewire::test(Reports::class)
        ->assertSee('Monday Madness')
        ->assertSee('Mon 5 Oct, 05:00')
        ->assertSee('30%')
        ->assertSee('Gold')
        ->assertSee('R520')
        ->assertSee('67%');
    expect($totals)->toMatchArray(['attended' => 2, 'noShows' => 1, 'attendanceRate' => 67]);
});

it('switches to another month', function () {
    $this->actingAs(admin());
    classSession(['title' => 'September Class', 'starts_at' => '2026-09-14 05:00']);

    Livewire::test(Reports::class)->assertDontSee('September Class')
        ->set('month', '2026-09')
        ->assertSee('September Class');
});

it('exports the sessions table as a CSV with a header and one line per session', function () {
    $this->actingAs(admin());
    $morning = classSession(['title' => 'Morning, "Fit"', 'starts_at' => '2026-10-05 05:00', 'capacity' => 10]);
    Booking::factory()->count(3)->for($morning, 'classSession')->attended()->create();
    classSession(['title' => 'Evening', 'starts_at' => '2026-10-26 16:00', 'capacity' => 15]);
    classSession(['title' => 'September', 'starts_at' => '2026-09-26 16:00']);

    $csv = "Date,Title,Capacity,Occupied,\"Fill %\",Attended\n"
        ."\"2026-10-05 05:00\",\"Morning, \"\"Fit\"\"\",10,3,30,3\n"
        ."\"2026-10-26 16:00\",Evening,15,0,0,0\n";

    Livewire::test(Reports::class)
        ->callAction('exportCsv')
        ->assertFileDownloaded('optifit-sessions-2026-10.csv', $csv);
});

it('names the CSV after the chosen month', function () {
    $this->actingAs(admin());
    classSession(['title' => 'September', 'starts_at' => '2026-09-26 16:00']);

    Livewire::test(Reports::class)
        ->set('month', '2026-09')
        ->callAction('exportCsv')
        ->assertFileDownloaded('optifit-sessions-2026-09.csv');
});
