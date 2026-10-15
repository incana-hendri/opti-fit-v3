<?php

use App\Enums\CreditEntryType;
use App\Livewire\MembershipPage;
use App\Mail\AdminMembershipEnding;
use App\Mail\MembershipEnding;
use App\Models\CreditEntry;
use App\Models\Membership;
use App\Models\Package;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
});

it('invites a customer without a package to choose one', function () {
    $this->actingAs(customer())->get('/membership')
        ->assertOk()
        ->assertSee('No package yet')
        ->assertSee('No credits yet')
        ->assertSee(route('membership.request'), false);
});

it('shows the current package with its covered members, credits and price (docs/04)', function () {
    $user = customer(2);
    activeMembership($user, '2026-09', ['monthly_credits' => 16, 'monthly_price_cents' => 78000]);
    $names = $user->household->members->pluck('first_name')->join(', ', ' and ');

    $this->actingAs($user)->get('/membership')
        ->assertOk()
        ->assertSee('Active since September')
        ->assertSee('16 per month')
        ->assertSee('R780 per month')
        ->assertSee($names)
        ->assertSee('Change package or members')
        ->assertSee('Cancel membership');
});

it('shows a pending request with a withdraw button (BR-4.3)', function () {
    $user = customer();
    $package = Package::factory()->create(['name' => 'Three times a week']);
    Membership::factory()->for($user->household)->for($package)->create();

    Livewire::actingAs($user)->test(MembershipPage::class)
        ->assertSee('Three times a week')
        ->assertSee('Withdraw request')
        ->assertSee("We'll email you once it's approved.", false);
});

it('withdraws the pending request (BR-4.3)', function () {
    $user = customer();
    $request = Membership::factory()->for($user->household)->for(Package::factory()->create())->create();

    Livewire::actingAs($user)->test(MembershipPage::class)
        ->call('confirmWithdraw', $request->id)
        ->assertSet('withdrawId', $request->id)
        ->call('withdraw')
        ->assertSet('withdrawId', null)
        ->assertDispatched('toast');

    expect(Membership::query()->count())->toBe(0);
});

it("can't withdraw someone else's request (BR-2.7)", function () {
    $theirs = Membership::factory()->for(customer()->household)->for(Package::factory()->create())->create();

    Livewire::actingAs(customer())->test(MembershipPage::class)
        ->call('confirmWithdraw', $theirs->id)
        ->assertNotFound();

    Livewire::actingAs(customer())->test(MembershipPage::class)
        ->set('withdrawId', $theirs->id)
        ->call('withdraw')
        ->assertNotFound();

    expect($theirs->fresh())->not->toBeNull();
});

it('cancels the membership for this month and tells everyone (BR-4.8)', function () {
    Mail::fake();
    $user = customer();
    $membership = activeMembership($user, '2026-09');

    Livewire::actingAs($user)->test(MembershipPage::class)
        ->call('confirmCancel')
        ->assertDispatched('open-sheet')
        ->call('cancel')
        ->assertDispatched('close-sheet')
        ->assertSee('Your membership ends on 31 Oct')
        ->assertSee('Undo cancellation')
        ->assertDontSee('Change package or members');

    expect($membership->fresh()->ends_period)->toBe('2026-10');
    Mail::assertQueued(MembershipEnding::class);
    Mail::assertQueued(AdminMembershipEnding::class);
});

it('undoes the cancellation (BR-4.8)', function () {
    $user = customer();
    $membership = activeMembership($user, '2026-09', ['ends_period' => '2026-10']);

    Livewire::actingAs($user)->test(MembershipPage::class)
        ->call('undoCancellation')
        ->assertDontSee('Your membership ends on')
        ->assertSee('Cancel membership');

    expect($membership->fresh()->ends_period)->toBeNull();
});

it('explains why cancelling is blocked while a package change is scheduled (BR-4.7, 4.8)', function () {
    $user = customer();
    activeMembership($user, '2026-09', ['ends_period' => '2026-10']);
    activeMembership($user, '2026-11');

    Livewire::actingAs($user)->test(MembershipPage::class)
        ->assertSee('Your package changes on 1 November')
        ->assertDontSee('Cancel membership')
        ->call('cancel')
        ->assertDispatched('toast', fn (string $event, array $params) => $params['type'] === 'error');
});

it('shows a package that starts next month to a customer with nothing running yet (BR-4.4)', function () {
    $user = customer();
    activeMembership($user, '2026-11');

    Livewire::actingAs($user)->test(MembershipPage::class)
        ->assertSee('Starts 1 November')
        ->assertDontSee('No package yet');
});

it('lists the credit history for this month and the two before, grouped by month (BR-5.8)', function () {
    $user = customer();
    $household = $user->household;
    CreditEntry::factory()->for($household)->forPeriod('2026-10')->create(['description' => 'October credits (2× per week)', 'amount' => 8]);
    CreditEntry::factory()->for($household)->forPeriod('2026-10')->ofType(CreditEntryType::Booking, -1)->create(['description' => 'Booked Mia']);
    CreditEntry::factory()->for($household)->forPeriod('2026-09')->create(['description' => 'September credits', 'amount' => 8]);
    CreditEntry::factory()->for($household)->forPeriod('2026-09')->ofType(CreditEntryType::Expiry, -5)->create(['description' => 'Unused September credits expired']);
    CreditEntry::factory()->for($household)->forPeriod('2026-08')->create(['description' => 'August credits']);
    CreditEntry::factory()->for($household)->forPeriod('2026-07')->create(['description' => 'July credits']);
    CreditEntry::factory()->forPeriod('2026-10')->create(['description' => 'Someone else\'s credits']);

    $this->actingAs($user)->get('/membership')
        ->assertOk()
        ->assertSeeInOrder(['October', 'Booked Mia', '−1', 'October credits (2× per week)', '+8', 'September', 'Unused September credits expired', 'September credits', 'Expired: 5', 'August', 'August credits'])
        ->assertDontSee('July credits')
        ->assertDontSee("Someone else's credits", false);
});

it('renders the membership page for every membership status', function (string $state) {
    $user = customer();
    match ($state) {
        'requested' => Membership::factory()->for($user->household)->for(Package::factory()->create())->create(),
        'active' => activeMembership($user),
        'paused' => Membership::factory()->for($user->household)->for(Package::factory()->create())->paused()->create(),
        'ending' => Membership::factory()->for($user->household)->for(Package::factory()->create())->ending()->create(),
        'cancelled' => Membership::factory()->for($user->household)->for(Package::factory()->create())->cancelled()->create(),
        'declined' => Membership::factory()->for($user->household)->for(Package::factory()->create())->declined()->create(),
    };

    $this->actingAs($user)->get('/membership')->assertOk();
})->with(['requested', 'active', 'paused', 'ending', 'cancelled', 'declined']);
