<?php

use App\Filament\Resources\Households\Pages\ListHouseholds;
use App\Filament\Resources\Households\Pages\ViewHousehold;
use App\Filament\Resources\Households\RelationManagers\MembersRelationManager;
use App\Notifications\ResetPassword;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(admin());
});

it('renders the households list and view pages', function () {
    $household = customer()->household;

    $this->get('/admin/households')->assertOk()->assertSee($household->name);
    $this->get("/admin/households/{$household->id}")->assertOk()->assertSee($household->user->email);
});

it('finds a household by any member name or email (docs/05)', function () {
    $smiths = customer()->household;
    $jones = customer()->household;
    $member = $smiths->members()->first();

    Livewire::test(ListHouseholds::class)
        ->searchTable($member->first_name)
        ->assertCanSeeTableRecords([$smiths])
        ->assertCanNotSeeTableRecords([$jones])
        ->searchTable($jones->user->email)
        ->assertCanSeeTableRecords([$jones])
        ->assertCanNotSeeTableRecords([$smiths]);
});

it('deactivates and reactivates a login (BR-1.8)', function () {
    $household = customer()->household;

    Livewire::test(ViewHousehold::class, ['record' => $household->getRouteKey()])
        ->callAction('deactivate')
        ->assertActionHidden('deactivate')
        ->assertActionVisible('reactivate');
    expect($household->user->fresh()->isDeactivated())->toBeTrue();

    Livewire::test(ViewHousehold::class, ['record' => $household->getRouteKey()])
        ->callAction('reactivate');
    expect($household->user->fresh()->isDeactivated())->toBeFalse();
});

it('sends a password reset email (docs/05)', function () {
    Notification::fake();
    $household = customer()->household;

    Livewire::test(ViewHousehold::class, ['record' => $household->getRouteKey()])
        ->callAction('sendPasswordReset');

    Notification::assertSentTo($household->user, ResetPassword::class);
});

it('edits a member including health notes', function () {
    $household = customer()->household;
    $member = $household->members()->first();

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $household, 'pageClass' => ViewHousehold::class])
        ->callAction(TestAction::make('edit')->table($member), [
            'first_name' => 'Mia',
            'health_notes' => 'Sore knee',
        ])
        ->assertHasNoFormErrors();

    expect($member->fresh()->first_name)->toBe('Mia')
        ->and($member->fresh()->health_notes)->toBe('Sore knee');
});

it('archives a member (BR-2.5)', function () {
    $household = customer()->household;
    $member = $household->members()->first();

    Livewire::test(MembersRelationManager::class, ['ownerRecord' => $household, 'pageClass' => ViewHousehold::class])
        ->callAction(TestAction::make('archive')->table($member));

    expect($member->fresh()->isArchived())->toBeTrue();
});
