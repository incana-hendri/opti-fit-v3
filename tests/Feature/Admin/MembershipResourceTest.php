<?php

use App\Enums\CreditEntryType;
use App\Enums\MembershipStatus;
use App\Filament\Resources\Memberships\MembershipResource;
use App\Filament\Resources\Memberships\Pages\ListMemberships;
use App\Mail\MembershipApproved;
use App\Mail\MembershipChangeApproved;
use App\Mail\MembershipDeclined;
use App\Models\CreditEntry;
use App\Models\Membership;
use App\Models\Package;
use App\Models\User;
use App\Support\CreditLedger;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $this->admin = admin();
    $this->actingAs($this->admin);
});

function requestedMembership(?User $user = null): Membership
{
    $user ??= customer(2);

    return Membership::factory()->for($user->household)->for(Package::factory()->create(['sessions_per_week' => 2, 'credits_per_member' => 8]))
        ->create(['monthly_credits' => 16, 'monthly_price_cents' => 78000]);
}

it('renders the memberships page', function () {
    requestedMembership();

    $this->get('/admin/memberships')->assertOk()->assertSee('Requested');
});

it('puts memberships on the right tab (docs/05)', function () {
    $requested = requestedMembership();
    $active = activeMembership(customer());
    $paused = Membership::factory()->for(customer()->household)->paused()->create();
    $ending = Membership::factory()->for(customer()->household)->ending()->create();
    $cancelled = Membership::factory()->for(customer()->household)->cancelled()->create();
    $declined = Membership::factory()->for(customer()->household)->declined()->create();

    Livewire::test(ListMemberships::class)
        ->assertCanSeeTableRecords([$requested])->assertCanNotSeeTableRecords([$active, $paused, $cancelled])
        ->set('activeTab', 'active')
        ->assertCanSeeTableRecords([$active, $ending])->assertCanNotSeeTableRecords([$requested, $paused])
        ->set('activeTab', 'paused')
        ->assertCanSeeTableRecords([$paused])->assertCanNotSeeTableRecords([$active])
        ->set('activeTab', 'ending')
        ->assertCanSeeTableRecords([$ending])->assertCanNotSeeTableRecords([$active])
        ->set('activeTab', 'closed')
        ->assertCanSeeTableRecords([$cancelled, $declined])->assertCanNotSeeTableRecords([$requested, $active]);
});

it('prefills the approval with this month and the prorated first month when it is the 20th or earlier (BR-4.4, 4.5)', function () {
    $membership = requestedMembership();

    Livewire::test(ListMemberships::class)
        ->mountAction(TestAction::make('approve')->table($membership))
        ->assertActionDataSet([
            'starts_period' => '2026-10',
            'monthly_credits' => 16,
            'monthly_price' => 780,
            'first_month_credits' => 8, // 16 × 16/31
        ]);
});

it('prefills the approval with next month after the 20th (BR-4.4)', function () {
    $this->travelTo(Carbon::parse('2026-10-21 10:00'));
    $membership = requestedMembership();

    Livewire::test(ListMemberships::class)
        ->mountAction(TestAction::make('approve')->table($membership))
        ->assertActionDataSet(['starts_period' => '2026-11']);
});

it('approves for this month, granting the prorated credits, and emails the customer (BR-4.4, C4)', function () {
    $membership = requestedMembership();

    Livewire::test(ListMemberships::class)
        ->callAction(TestAction::make('approve')->table($membership), [
            'starts_period' => '2026-10',
            'monthly_credits' => 16,
            'monthly_price' => 780,
            'first_month_credits' => 8,
            'admin_note' => 'EFT ref 99',
        ])
        ->assertHasNoActionErrors();

    $membership->refresh();
    expect($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->starts_period)->toBe('2026-10')
        ->and($membership->approved_by)->toBe($this->admin->id)
        ->and($membership->admin_note)->toBe('EFT ref 99')
        ->and(CreditLedger::balance($membership->household))->toBe(8);
    Mail::assertQueued(MembershipApproved::class);
});

it('lets the admin change the credits, price and first month before approving (BR-4.4)', function () {
    $membership = requestedMembership();

    Livewire::test(ListMemberships::class)
        ->callAction(TestAction::make('approve')->table($membership), [
            'starts_period' => '2026-10',
            'monthly_credits' => 20,
            'monthly_price' => 850.50,
            'first_month_credits' => 0,
        ]);

    $membership->refresh();
    expect($membership->monthly_credits)->toBe(20)
        ->and($membership->monthly_price_cents)->toBe(85050)
        ->and(CreditEntry::query()->count())->toBe(0);
});

it('approves for next month without granting now (BR-4.4)', function () {
    $membership = requestedMembership();

    Livewire::test(ListMemberships::class)
        ->callAction(TestAction::make('approve')->table($membership), [
            'starts_period' => '2026-11',
            'monthly_credits' => 16,
            'monthly_price' => 780,
        ]);

    expect($membership->fresh()->starts_period)->toBe('2026-11')
        ->and(CreditEntry::query()->count())->toBe(0);
});

it('fixes a change request to next month and ends the current package (BR-4.7)', function () {
    $user = customer();
    $old = activeMembership($user, '2026-09');
    $change = requestedMembership($user);

    Livewire::test(ListMemberships::class)
        ->mountAction(TestAction::make('approve')->table($change))
        ->assertActionDataSet(['starts_period' => '2026-11'])
        ->assertMountedActionModalSee('Current package ends 31 Oct')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($old->fresh()->ends_period)->toBe('2026-10')
        ->and($change->fresh()->starts_period)->toBe('2026-11')
        ->and(CreditEntry::query()->count())->toBe(0);
    Mail::assertQueued(MembershipChangeApproved::class);
});

it('shows an error instead of failing when the action refuses (BR-4.1)', function () {
    $user = customer();
    activeMembership($user, '2026-09', ['ends_period' => '2026-10']);
    activeMembership($user, '2026-11');
    $another = requestedMembership($user);

    Livewire::test(ListMemberships::class)
        ->callAction(TestAction::make('approve')->table($another), [
            'starts_period' => '2026-11',
            'monthly_credits' => 16,
            'monthly_price' => 780,
        ])
        ->assertNotified('This household already has a package starting next month. Cancel that one first.');

    expect($another->fresh()->status)->toBe(MembershipStatus::Requested);
});

it('declines with a required reason and emails the customer (BR-4.6, C5)', function () {
    $membership = requestedMembership();

    Livewire::test(ListMemberships::class)
        ->callAction(TestAction::make('decline')->table($membership), ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);
    expect($membership->fresh()->status)->toBe(MembershipStatus::Requested);

    Livewire::test(ListMemberships::class)
        ->callAction(TestAction::make('decline')->table($membership), ['reason' => 'Fully booked'])
        ->assertHasNoActionErrors();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Declined)
        ->and($membership->fresh()->decline_reason)->toBe('Fully booked');
    Mail::assertQueued(MembershipDeclined::class);
});

it('offers approve and decline only on requests, and pause, resume, cancel and edit only on running memberships (docs/05)', function () {
    $requested = requestedMembership();
    $active = activeMembership(customer());
    $paused = Membership::factory()->for(customer()->household)->paused()->create();
    $cancelled = Membership::factory()->for(customer()->household)->cancelled()->create();

    $list = Livewire::test(ListMemberships::class);
    $list->assertActionVisible(TestAction::make('approve')->table($requested))
        ->assertActionVisible(TestAction::make('decline')->table($requested))
        ->assertActionHidden(TestAction::make('pause')->table($requested))
        ->assertActionHidden(TestAction::make('edit')->table($requested));

    $list->set('activeTab', 'active')
        ->assertActionHidden(TestAction::make('approve')->table($active))
        ->assertActionVisible(TestAction::make('pause')->table($active))
        ->assertActionHidden(TestAction::make('resume')->table($active))
        ->assertActionVisible(TestAction::make('cancelNow')->table($active))
        ->assertActionVisible(TestAction::make('edit')->table($active));

    $list->set('activeTab', 'paused')
        ->assertActionVisible(TestAction::make('resume')->table($paused))
        ->assertActionHidden(TestAction::make('pause')->table($paused));

    $list->set('activeTab', 'closed')
        ->assertActionHidden(TestAction::make('cancelNow')->table($cancelled))
        ->assertActionHidden(TestAction::make('edit')->table($cancelled));
});

it('pauses and resumes a membership (BR-4.9)', function () {
    $membership = activeMembership(customer(), '2026-09');

    Livewire::test(ListMemberships::class)
        ->set('activeTab', 'active')
        ->callAction(TestAction::make('pause')->table($membership));
    expect($membership->fresh()->status)->toBe(MembershipStatus::Paused);

    Livewire::test(ListMemberships::class)
        ->set('activeTab', 'paused')
        ->callAction(TestAction::make('resume')->table($membership));
    expect($membership->fresh()->status)->toBe(MembershipStatus::Active)
        ->and($membership->fresh()->starts_period)->toBe('2026-09')
        ->and($membership->fresh()->grants_from_period)->toBe('2026-11'); // no grant mid-month
});

it('cancels a membership now without touching the ledger (BR-4.9)', function () {
    $user = customer();
    $membership = activeMembership($user, '2026-09');
    CreditLedger::write($user->household, '2026-10', CreditEntryType::Grant, 8, 'October credits', $membership);

    Livewire::test(ListMemberships::class)
        ->set('activeTab', 'active')
        ->callAction(TestAction::make('cancelNow')->table($membership));

    expect($membership->fresh()->status)->toBe(MembershipStatus::Cancelled)
        ->and(CreditLedger::balance($user->household))->toBe(8);
});

it('edits credits, price, members and note (BR-4.9)', function () {
    $user = customer(3);
    $membership = activeMembership($user, '2026-09');
    $keep = $user->household->members->first();

    Livewire::test(ListMemberships::class)
        ->set('activeTab', 'active')
        ->mountAction(TestAction::make('edit')->table($membership))
        ->assertActionDataSet(['monthly_credits' => 8, 'monthly_price' => 520])
        ->setActionData(['monthly_credits' => 12, 'monthly_price' => 600, 'member_ids' => [$keep->id], 'admin_note' => 'Paid ahead'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $membership->refresh();
    expect($membership->monthly_credits)->toBe(12)
        ->and($membership->monthly_price_cents)->toBe(60000)
        ->and($membership->admin_note)->toBe('Paid ahead')
        ->and($membership->members->pluck('id')->all())->toBe([$keep->id]);
});

it('shows the new-request count in the navigation (docs/05)', function () {
    requestedMembership();
    requestedMembership();

    expect(MembershipResource::getNavigationBadge())->toBe('2');
});
