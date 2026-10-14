<?php

use App\Enums\CreditEntryType;
use App\Livewire\Home;
use App\Models\CreditEntry;
use App\Models\Membership;
use App\Models\Package;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
});

it('invites a customer without a package to choose one (docs/04)', function () {
    $this->actingAs(customer())->get('/home')
        ->assertOk()
        ->assertSee('No package yet')
        ->assertSee('Choose a package')
        ->assertSee(route('membership.request'), false);
});

it('says the request is pending while it waits for approval (docs/04)', function () {
    $user = customer();
    Membership::factory()->for($user->household)->for(Package::factory()->create(['name' => 'Twice a week']))->create();

    $this->actingAs($user)->get('/home')
        ->assertOk()
        ->assertSee('Request pending')
        ->assertDontSee('No package yet');
});

it('shows the balance, month and expiry date once credits arrive (BR-5.8)', function () {
    $user = customer();
    activeMembership($user, '2026-10');
    CreditEntry::factory()->for($user->household)->create(['amount' => 8]);
    CreditEntry::factory()->for($user->household)->forPeriod('2026-09')->create(['amount' => 6]);

    Livewire::actingAs($user)->test(Home::class)
        ->assertSee('8')
        ->assertSee('for October · expire 31 Oct')
        ->assertSee('0 of 8 used this month')
        ->assertViewHas('balance', 8)
        ->assertViewHas('granted', 8)
        ->assertViewHas('used', 0);
});

it('works out used credits from bookings less refunds, and counts positive adjustments as granted', function () {
    $user = customer();
    $household = $user->household;
    activeMembership($user, '2026-10');
    CreditEntry::factory()->for($household)->create(['amount' => 8]);
    CreditEntry::factory()->for($household)->ofType(CreditEntryType::Adjustment, 2)->create();
    CreditEntry::factory()->for($household)->ofType(CreditEntryType::Adjustment, -1)->create();
    CreditEntry::factory()->for($household)->ofType(CreditEntryType::Booking, -3)->create();
    CreditEntry::factory()->for($household)->ofType(CreditEntryType::Refund, 1)->create();

    Livewire::actingAs($user)->test(Home::class)
        ->assertViewHas('balance', 7)
        ->assertViewHas('granted', 10)
        ->assertViewHas('used', 2);
});

it('shows the credit card for a household that still has credits but no membership (BR-5.8)', function () {
    $user = customer();
    CreditEntry::factory()->for($user->household)->create(['amount' => 3]);

    Livewire::actingAs($user)->test(Home::class)
        ->assertViewHas('hasCredits', true)
        ->assertDontSee('No package yet');
});

it('shows the credit card with zero credits for an active membership', function () {
    $user = customer();
    activeMembership($user, '2026-10');

    Livewire::actingAs($user)->test(Home::class)->assertViewHas('hasCredits', true)->assertViewHas('balance', 0);
});

it('warns that the membership is ending and lets the customer undo (BR-4.8)', function () {
    $user = customer();
    $membership = activeMembership($user, '2026-09', ['ends_period' => '2026-10']);

    Livewire::actingAs($user)->test(Home::class)
        ->assertSee('Your membership ends on 31 Oct')
        ->call('undoCancellation')
        ->assertDontSee('Your membership ends on');

    expect($membership->fresh()->ends_period)->toBeNull();
});

it('announces a scheduled package change (BR-4.7)', function () {
    $user = customer();
    activeMembership($user, '2026-09', ['ends_period' => '2026-10']);
    activeMembership($user, '2026-11');

    Livewire::actingAs($user)->test(Home::class)
        ->assertSee('Your package changes on 1 November')
        ->assertDontSee('Your membership ends on');
});

it('renders for every membership status', function (string $state) {
    $user = customer();
    $package = Package::factory()->create();
    match ($state) {
        'paused' => Membership::factory()->for($user->household)->for($package)->paused()->create(),
        'cancelled' => Membership::factory()->for($user->household)->for($package)->cancelled()->create(),
        'declined' => Membership::factory()->for($user->household)->for($package)->declined()->create(),
        'scheduled' => activeMembership($user, '2026-11'),
    };

    $this->actingAs($user)->get('/home')->assertOk();
})->with(['paused', 'cancelled', 'declined', 'scheduled']);
