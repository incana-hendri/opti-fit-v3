<?php

use App\Enums\CreditEntryType;
use App\Filament\Resources\Households\Pages\ListHouseholds;
use App\Filament\Resources\Households\Pages\ViewHousehold;
use App\Filament\Resources\Households\RelationManagers\CreditsRelationManager;
use App\Filament\Resources\Households\RelationManagers\MembershipsRelationManager;
use App\Models\CreditEntry;
use App\Models\Household;
use App\Models\Membership;
use App\Support\CreditLedger;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $this->admin = admin();
    $this->actingAs($this->admin);
});

function creditsTab(Household $household)
{
    return Livewire::test(CreditsRelationManager::class, ['ownerRecord' => $household, 'pageClass' => ViewHousehold::class]);
}

it('shows the credits and memberships tabs on the household page', function () {
    $user = customer();
    activeMembership($user);

    $this->get("/admin/households/{$user->household->id}")->assertOk();
    creditsTab($user->household)->assertOk();
    Livewire::test(MembershipsRelationManager::class, ['ownerRecord' => $user->household, 'pageClass' => ViewHousehold::class])->assertOk();
});

it('lists the whole ledger newest first with the current balance (docs/05)', function () {
    $household = customer()->household;
    $old = CreditEntry::factory()->for($household)->forPeriod('2026-09')->create(['description' => 'September credits']);
    $new = CreditEntry::factory()->for($household)->create(['description' => 'October credits']);

    creditsTab($household)
        ->assertCanSeeTableRecords([$new, $old], inOrder: true)
        ->assertSee('Balance for October: 8')
        ->filterTable('period', '2026-09')
        ->assertCanSeeTableRecords([$old])
        ->assertCanNotSeeTableRecords([$new]);
});

it('adjusts credits with a reason (BR-6.5)', function () {
    $household = customer()->household;

    creditsTab($household)
        ->callAction(TestAction::make('adjust')->table(), ['amount' => 3, 'reason' => 'Make-up class'])
        ->assertHasNoActionErrors();

    $entry = CreditEntry::query()->sole();
    expect($entry->type)->toBe(CreditEntryType::Adjustment)
        ->and($entry->amount)->toBe(3)
        ->and($entry->description)->toBe('Adjustment: Make-up class')
        ->and($entry->created_by)->toBe($this->admin->id)
        ->and(CreditLedger::balance($household))->toBe(3);
});

it('needs a reason and an amount other than 0 for an adjustment (BR-6.5)', function () {
    $household = customer()->household;

    creditsTab($household)
        ->callAction(TestAction::make('adjust')->table(), ['amount' => 0, 'reason' => 'Nothing'])
        ->assertHasActionErrors(['amount']);
    creditsTab($household)
        ->callAction(TestAction::make('adjust')->table(), ['amount' => 2, 'reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect(CreditEntry::query()->count())->toBe(0);
});

it('refuses a negative adjustment that would take the balance below zero (BR-6.5)', function () {
    $household = customer()->household;
    CreditEntry::factory()->for($household)->create(['amount' => 2]);

    creditsTab($household)
        ->callAction(TestAction::make('adjust')->table(), ['amount' => -5, 'reason' => 'Correction'])
        ->assertNotified();

    expect(CreditLedger::balance($household))->toBe(2)
        ->and(CreditEntry::query()->count())->toBe(1);
});

it('has no way to edit or delete ledger rows (BR-5.3)', function () {
    $household = customer()->household;
    $entry = CreditEntry::factory()->for($household)->create();

    creditsTab($household)
        ->assertActionDoesNotExist(TestAction::make('edit')->table($entry))
        ->assertActionDoesNotExist(TestAction::make('delete')->table($entry));
});

it('lists the membership history of a household (docs/05)', function () {
    $user = customer();
    $old = Membership::factory()->for($user->household)->cancelled()->create();
    $current = activeMembership($user);

    Livewire::test(MembershipsRelationManager::class, ['ownerRecord' => $user->household, 'pageClass' => ViewHousehold::class])
        ->assertCanSeeTableRecords([$current, $old]);
});

it('shows each household\'s package, status and balance in the list (docs/05)', function () {
    $with = customer();
    $membership = activeMembership($with);
    CreditEntry::factory()->for($with->household)->create(['amount' => 6]);
    $without = customer();

    Livewire::test(ListHouseholds::class)
        ->assertCanSeeTableRecords([$with->household, $without->household])
        ->assertTableColumnStateSet('membership', 'Active', $with->household)
        ->assertTableColumnStateSet('membership', 'None', $without->household)
        ->assertTableColumnStateSet('current_balance', 6, $with->household)
        ->assertTableColumnStateSet('current_balance', 0, $without->household)
        ->assertSee($membership->package->name);
});
