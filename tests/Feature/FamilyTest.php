<?php

use App\Livewire\Family;
use App\Models\Member;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('renders the family page', function () {
    $user = customer(2);

    $this->actingAs($user)->get('/family')
        ->assertOk()
        ->assertSee($user->household->members->first()->first_name);
});

it('adds a member (BR-2.3, BR-2.4)', function () {
    $user = customer();

    Livewire::actingAs($user)->test(Family::class)
        ->call('addMember')
        ->set('memberForm.first_name', 'Mia')
        ->set('memberForm.date_of_birth', '2015-08-19')
        ->set('memberForm.gender', 'female')
        ->call('saveMember')
        ->assertHasNoErrors();

    expect($user->household->members()->count())->toBe(2);
});

it('edits a member', function () {
    $user = customer();
    $member = $user->household->members()->first();

    Livewire::actingAs($user)->test(Family::class)
        ->call('editMember', $member->id)
        ->assertSet('memberForm.first_name', $member->first_name)
        ->set('memberForm.first_name', 'Renamed')
        ->call('saveMember')
        ->assertHasNoErrors();

    expect($member->fresh()->first_name)->toBe('Renamed');
});

it('stores health notes encrypted (BR-2.3)', function () {
    $user = customer();
    $member = $user->household->members()->first();

    Livewire::actingAs($user)->test(Family::class)
        ->call('editMember', $member->id)
        ->set('memberForm.health_notes', 'Mild asthma')
        ->call('saveMember');

    $stored = DB::table('members')->where('id', $member->id)->value('health_notes');
    expect($stored)->not->toContain('asthma')
        ->and($member->fresh()->health_notes)->toBe('Mild asthma');
});

it('archives a member, who then leaves the active list (BR-2.5)', function () {
    $user = customer(2);
    $member = $user->household->members()->first();

    Livewire::actingAs($user)->test(Family::class)
        ->call('confirmArchive', $member->id)
        ->call('runPending');

    expect($member->fresh()->isArchived())->toBeTrue()
        ->and($user->household->activeMembers()->count())->toBe(1);
});

it('lists archived members separately (BR-2.5)', function () {
    $user = customer();
    Member::factory()->archived()->for($user->household)->create(['first_name' => 'Archie']);

    Livewire::actingAs($user)->test(Family::class)
        ->assertViewHas('activeMembers', fn ($members) => $members->doesntContain('first_name', 'Archie'))
        ->assertViewHas('archivedMembers', fn ($members) => $members->contains('first_name', 'Archie'))
        ->assertSee('Archived (1)');
});

it('deletes a member without bookings (BR-2.5)', function () {
    $user = customer(2);
    $member = $user->household->members()->first();

    Livewire::actingAs($user)->test(Family::class)
        ->call('confirmDelete', $member->id)
        ->call('runPending');

    expect(Member::query()->find($member->id))->toBeNull();
});

it('sends a household with no active members back to onboarding', function () {
    $user = customer();
    $member = $user->household->members()->first();

    Livewire::actingAs($user)->test(Family::class)
        ->call('confirmArchive', $member->id)
        ->call('runPending');

    $this->actingAs($user)->get('/family')->assertRedirect(route('onboarding'));
});

it('saves the emergency contact and household name (BR-2.6)', function () {
    $user = customer();

    Livewire::actingAs($user)->test(Family::class)
        ->set('emergencyName', 'Johan Smith')
        ->set('emergencyPhone', '+27 83 765 4321')
        ->call('saveEmergencyContact')
        ->assertHasNoErrors()
        ->set('householdName', 'The Smiths')
        ->call('saveHouseholdName')
        ->assertHasNoErrors();

    $household = $user->household->fresh();
    expect($household->emergency_contact_name)->toBe('Johan Smith')
        ->and($household->emergency_contact_phone)->toBe('+27 83 765 4321')
        ->and($household->name)->toBe('The Smiths');
});

it('validates the emergency contact and household name', function () {
    Livewire::actingAs(customer())->test(Family::class)
        ->set('emergencyName', '')
        ->set('emergencyPhone', 'none')
        ->call('saveEmergencyContact')
        ->assertHasErrors(['emergencyName', 'emergencyPhone'])
        ->set('householdName', '')
        ->call('saveHouseholdName')
        ->assertHasErrors(['householdName']);
});

it("can't touch another household's member (BR-2.7)", function (string $action) {
    $someoneElses = customer()->household->members()->first();

    Livewire::actingAs(customer())->test(Family::class)->call($action, $someoneElses->id)
        ->assertNotFound();

    expect($someoneElses->fresh()->isArchived())->toBeFalse();
})->with(['editMember', 'confirmArchive', 'confirmDelete']);

it("can't save over another household's member by tampering with the form (BR-2.7)", function () {
    $someoneElses = customer()->household->members()->first();

    Livewire::actingAs(customer())->test(Family::class)
        ->call('addMember')
        ->set('memberForm.memberId', $someoneElses->id)
        ->set('memberForm.first_name', 'Hijacked')
        ->set('memberForm.date_of_birth', '2015-08-19')
        ->set('memberForm.gender', 'female')
        ->call('saveMember')
        ->assertNotFound();

    expect($someoneElses->fresh()->first_name)->not->toBe('Hijacked');
});

it("can't archive another household's member by tampering with the pending action (BR-2.7)", function () {
    $someoneElses = customer()->household->members()->first();

    Livewire::actingAs(customer())->test(Family::class)
        ->set('pending', ['action' => 'archive', 'memberId' => $someoneElses->id])
        ->call('runPending')
        ->assertNotFound();

    expect($someoneElses->fresh()->isArchived())->toBeFalse();
});

it('marks the members covered by the active membership (BR-4.10)', function () {
    $user = customer(2);
    $covered = $user->household->members->first();
    $membership = activeMembership($user);
    $membership->members()->sync([$covered->id]);

    Livewire::actingAs($user)->test(Family::class)
        ->assertViewHas('coveredIds', [$covered->id])
        ->assertSee('Covered by your package');
});

it('marks nobody as covered without an active membership (BR-4.10)', function () {
    $user = customer();
    Membership::factory()->for($user->household)->paused()->create();

    Livewire::actingAs($user)->test(Family::class)
        ->assertViewHas('coveredIds', [])
        ->assertDontSee('Covered by your package');
});
