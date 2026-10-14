<?php

use App\Enums\Gender;
use App\Livewire\Onboarding;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Livewire\Livewire;

function newCustomer(): User
{
    return User::factory()->create(['first_name' => 'Anna', 'last_name' => 'Smith', 'date_of_birth' => '1986-04-02']);
}

it('sends customers without a finished household to onboarding', function (string $path) {
    $this->actingAs(newCustomer())->get($path)->assertRedirect(route('onboarding'));
})->with(['/home', '/family', '/account']);

it('renders onboarding for a new customer', function () {
    $this->actingAs(newCustomer())->get('/onboarding')->assertOk()->assertSee('About you');
});

it('creates the household and an account-holder member when they train too (BR-2.1, BR-2.2)', function () {
    $user = newCustomer();

    Livewire::actingAs($user)->test(Onboarding::class)
        ->assertSet('householdName', 'Smith family')
        ->set('trains', 'yes')
        ->set('holderGender', 'female')
        ->call('saveAboutYou')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    $household = $user->household()->sole();
    $holder = $household->members()->sole();

    expect($household->name)->toBe('Smith family')
        ->and($holder->is_account_holder)->toBeTrue()
        ->and($holder->first_name)->toBe('Anna')
        ->and($holder->date_of_birth->toDateString())->toBe('1986-04-02')
        ->and($holder->gender)->toBe(Gender::Female);
});

it('creates no member for an account holder who does not train (BR-2.2)', function () {
    $user = newCustomer();

    Livewire::actingAs($user)->test(Onboarding::class)
        ->set('trains', 'no')
        ->set('householdName', 'The Smiths')
        ->call('saveAboutYou')
        ->assertSet('step', 2);

    expect($user->household()->sole()->name)->toBe('The Smiths')
        ->and(Member::query()->count())->toBe(0);
});

it('removes the account-holder member when they change their answer to no', function () {
    $user = newCustomer();
    $component = Livewire::actingAs($user)->test(Onboarding::class)
        ->set('trains', 'yes')->set('holderGender', 'female')->call('saveAboutYou');

    $component->call('back')->set('trains', 'no')->call('saveAboutYou');

    expect(Member::query()->count())->toBe(0)
        ->and(Household::query()->count())->toBe(1);
});

it('validates step one', function () {
    Livewire::actingAs(newCustomer())->test(Onboarding::class)
        ->set('householdName', '')
        ->call('saveAboutYou')
        ->assertHasErrors(['trains', 'householdName']);

    Livewire::actingAs(newCustomer())->test(Onboarding::class)
        ->set('trains', 'yes')
        ->call('saveAboutYou')
        ->assertHasErrors(['holderGender']);
});

it('needs at least one family member before step three', function () {
    $user = newCustomer();
    $user->household()->create(['name' => 'Smith family']);

    Livewire::actingAs($user)->test(Onboarding::class)
        ->assertSet('step', 2)
        ->call('toEmergencyContact')
        ->assertHasErrors('members')
        ->assertSet('step', 2);
});

it('adds family members with optional health notes (BR-2.3)', function () {
    $user = newCustomer();
    $user->household()->create(['name' => 'Smith family']);

    Livewire::actingAs($user)->test(Onboarding::class)
        ->call('addMember')
        ->assertSet('memberForm.last_name', 'Smith')
        ->set('memberForm.first_name', 'Mia')
        ->set('memberForm.date_of_birth', '2015-08-19')
        ->set('memberForm.gender', 'female')
        ->set('memberForm.health_notes', 'Mild asthma')
        ->call('saveMember')
        ->assertHasNoErrors()
        ->call('toEmergencyContact')
        ->assertSet('step', 3);

    $mia = $user->household->members()->sole();
    expect($mia->first_name)->toBe('Mia')
        ->and($mia->is_account_holder)->toBeFalse()
        ->and($mia->health_notes)->toBe('Mild asthma');
});

it('requires the member fields (BR-2.3)', function () {
    $user = newCustomer();
    $user->household()->create(['name' => 'Smith family']);

    Livewire::actingAs($user)->test(Onboarding::class)
        ->call('addMember')
        ->set('memberForm.last_name', '')
        ->set('memberForm.date_of_birth', today()->addDay()->toDateString())
        ->call('saveMember')
        ->assertHasErrors(['memberForm.first_name', 'memberForm.last_name', 'memberForm.date_of_birth', 'memberForm.gender']);
});

it('saves the emergency contact and finishes (BR-2.6)', function () {
    $user = newCustomer();
    $household = Household::factory()->withoutEmergencyContact()->onboarded()->for($user)->create();

    Livewire::actingAs($user)->test(Onboarding::class)
        ->assertSet('step', 3)
        ->set('emergencyName', 'Johan Smith')
        ->set('emergencyPhone', '083 765 4321')
        ->call('finish')
        ->assertRedirect(route('membership.request'));

    expect($household->fresh()->emergency_contact_name)->toBe('Johan Smith')
        ->and($household->fresh()->emergency_contact_phone)->toBe('083 765 4321');
});

it('validates the emergency contact (BR-2.6)', function () {
    $user = newCustomer();
    Household::factory()->withoutEmergencyContact()->onboarded()->for($user)->create();

    Livewire::actingAs($user)->test(Onboarding::class)
        ->set('emergencyPhone', 'call me')
        ->call('finish')
        ->assertHasErrors(['emergencyName', 'emergencyPhone']);
});

it('sends a finished household from onboarding to home', function () {
    $this->actingAs(customer())->get('/onboarding')->assertRedirect(route('home'));
});

it("can't edit or remove another household's member during onboarding (BR-2.7)", function (string $action) {
    $user = newCustomer();
    $user->household()->create(['name' => 'Smith family']);
    $someoneElses = customer()->household->members()->first();

    Livewire::actingAs($user)->test(Onboarding::class)->call($action, $someoneElses->id)
        ->assertNotFound();

    expect($someoneElses->fresh())->not->toBeNull();
})->with(['editMember', 'removeMember']);
