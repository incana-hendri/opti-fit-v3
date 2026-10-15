<?php

use App\Enums\CreditEntryType;
use App\Filament\Resources\Households\Pages\ListHouseholds;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\ThisMonth;
use App\Filament\Widgets\TodaysClasses;
use App\Models\Booking;
use App\Models\CreditEntry;
use App\Models\GuestEntry;
use App\Models\Household;
use App\Models\Membership;
use App\Models\Package;
use App\Support\Period;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// docs/05 § Dashboard: the numbers are checked against hand-built fixtures.
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-20 12:00'));
    $this->actingAs(admin());
});

it('renders the dashboard in the Today group with the three widgets in order', function () {
    $this->get('/admin')
        ->assertOk()
        ->assertSeeInOrder(["Today's classes", 'Needs attention', 'October so far'])
        ->assertSeeInOrder(['Today', 'Dashboard'])
        ->assertDontSee('Welcome');
});

it('lists today\'s and tomorrow\'s scheduled classes with places and a below-minimum warning, linking to the roster', function () {
    $quiet = classSession(['title' => 'Quiet Lunch', 'starts_at' => '2026-10-20 17:00', 'min_participants' => 3]);
    Booking::factory()->for($quiet, 'classSession')->create();
    $busy = classSession(['title' => 'Busy Morning', 'starts_at' => '2026-10-21 05:00', 'min_participants' => 1, 'capacity' => 12]);
    Booking::factory()->count(2)->for($busy, 'classSession')->create();
    GuestEntry::factory()->for($busy)->create(['participant_count' => 2]);
    classSession(['title' => 'Called Off', 'starts_at' => '2026-10-20 18:00'])->update(['status' => 'cancelled']);
    classSession(['title' => 'Day After', 'starts_at' => '2026-10-22 05:00']);
    classSession(['title' => 'Last Night', 'starts_at' => '2026-10-19 17:00']);

    Livewire::test(TodaysClasses::class)
        ->assertSeeInOrder(['Today', 'Quiet Lunch', '1/15', 'Below minimum (3)', 'Tomorrow', 'Busy Morning', '2/12', '+2 guests'])
        ->assertSee(route('filament.admin.resources.class-sessions.view', $quiet))
        ->assertDontSee('Called Off')
        ->assertDontSee('Day After')
        ->assertDontSee('Last Night');
});

it('does not warn about the minimum for a class that has started', function () {
    classSession(['title' => 'Under Way', 'starts_at' => '2026-10-20 11:30', 'min_participants' => 5]);

    Livewire::test(TodaysClasses::class)->assertSee('Under Way')->assertDontSee('Below minimum');
});

it('says when there are no classes', function () {
    Livewire::test(TodaysClasses::class)->assertSee('No classes today or tomorrow.');
});

it('counts what needs attention, with links, and shows zeros', function () {
    Livewire::test(NeedsAttention::class)
        ->assertSee('Membership requests')
        ->assertSee('Deletion requests')
        ->assertSee('Ending this month')
        ->assertSee(route('filament.admin.resources.memberships.index', ['tab' => 'requested']), false)
        ->assertSee(route('filament.admin.resources.memberships.index', ['tab' => 'ending']), false);

    Membership::factory()->count(2)->create();
    Membership::factory()->active()->ending()->create();
    Membership::factory()->active()->create();
    $asked = customer();
    $asked->forceFill(['deletion_requested_at' => now()])->save();
    $done = customer();
    $done->forceFill(['deletion_requested_at' => now()])->save();
    $done->household->forceFill(['anonymised_at' => now()])->save();

    $widget = Livewire::test(NeedsAttention::class);
    $html = preg_replace('/\s+/', ' ', strip_tags($widget->html()));

    expect($html)->toContain('Membership requests 2')
        ->toContain('Deletion requests 1')
        ->toContain('Ending this month 1');
});

it('filters the households list to the deletion requests the dashboard links to (BR-1.6)', function () {
    $asked = customer();
    $asked->forceFill(['deletion_requested_at' => now()])->save();
    $other = customer();

    Livewire::withQueryParams(['filters' => ['deletion_requested' => ['isActive' => true]]])
        ->test(ListHouseholds::class)
        ->assertCanSeeTableRecords([$asked->household])
        ->assertCanNotSeeTableRecords([$other->household]);
});

it('shows this month\'s numbers (the month so far)', function () {
    $package = Package::factory()->create();
    Membership::factory()->for($package)->active()->state(['monthly_price_cents' => 52000])->create();
    Membership::factory()->for($package)->active()->ending()->state(['monthly_price_cents' => 28000])->create();
    Membership::factory()->for($package)->paused()->create();

    $settled = classSession(['starts_at' => '2026-10-05 05:00']);
    Booking::factory()->count(3)->for($settled, 'classSession')->attended()->create();
    Booking::factory()->for($settled, 'classSession')->noShow()->create();
    Booking::factory()->for($settled, 'classSession')->create(); // booked, older than 24 hours: attended (BR-7.9)
    $called = classSession(['starts_at' => '2026-10-08 05:00']);
    $called->update(['status' => 'cancelled']);
    Booking::factory()->count(2)->for($called, 'classSession')->cancelled()->create();

    $household = Household::factory()->create();
    CreditEntry::factory()->for($household)->create(['amount' => 20]);
    CreditEntry::factory()->for($household)->ofType(CreditEntryType::Booking, -5)->create();
    CreditEntry::factory()->for($household)->ofType(CreditEntryType::Refund, 1)->create();
    CreditEntry::factory()->for($household)->forPeriod(Period::previous())->ofType(CreditEntryType::Expiry, -9)->create();

    $html = preg_replace('/\s+/', ' ', strip_tags(Livewire::test(ThisMonth::class)->html()));

    expect($html)->toContain('Active memberships 2')
        ->toContain('Expected monthly income R800')
        ->toContain('Bookings 5')
        ->toContain('Attendance rate')
        ->toContain('80%')
        ->toContain('Credits granted 20')
        ->toContain('Credits used 4')
        ->toContain('Credits expired 0');
});

it('shows a dash for the attendance rate before any class has been held', function () {
    $html = preg_replace('/\s+/', ' ', strip_tags(Livewire::test(ThisMonth::class)->html()));

    expect($html)->toContain('Attendance rate —');
});
