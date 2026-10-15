<?php

use App\Livewire\RequestForm;
use App\Mail\AdminMembershipRequest;
use App\Mail\MembershipRequested;
use App\Models\Membership;
use App\Models\Package;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->package = Package::factory()->pricedAt([1 => 52000, 2 => 78000, 3 => 99800])->create(['name' => 'Twice a week', 'credits_per_member' => 8]);
});

it('renders the request form for a customer (BR-4.2)', function () {
    $this->actingAs(customer(2))->get('/membership/request')
        ->assertOk()
        ->assertSee('Choose your package')
        ->assertSee('Twice a week')
        ->assertSee("We'll confirm and send payment details by email.", false);
});

it('preselects the package from the pricing page and all active members', function () {
    $user = customer(2);

    Livewire::actingAs($user)->withQueryParams(['package' => $this->package->id])->test(RequestForm::class)
        ->assertSet('packageId', $this->package->id)
        ->assertSet('memberIds', $user->household->activeMembers()->pluck('id')->all());
});

it('ignores an archived package in the query string (BR-3.3)', function () {
    $archived = Package::factory()->inactive()->create();

    Livewire::actingAs(customer())->withQueryParams(['package' => $archived->id])->test(RequestForm::class)
        ->assertSet('packageId', null);
});

it('shows the credits and price for the chosen members live (BR-4.2)', function () {
    $user = customer(3);
    $ids = $user->household->members->pluck('id')->all();

    Livewire::actingAs($user)->test(RequestForm::class)
        ->set('packageId', $this->package->id)
        ->set('memberIds', [$ids[0]])
        ->assertSee('8 credits / month')->assertSee('R520 / month')
        ->set('memberIds', [$ids[0], $ids[1]])
        ->assertSee('16 credits / month')->assertSee('R780 / month')
        ->set('memberIds', $ids)
        ->assertSee('24 credits / month')->assertSee('R998 / month');
});

it('does not list archived members (BR-2.5)', function () {
    $user = customer(2);
    $archived = $user->household->members->first();
    $archived->forceFill(['archived_at' => now()])->save();

    Livewire::actingAs($user)->test(RequestForm::class)
        ->assertDontSee($archived->fullName())
        ->assertSet('memberIds', [$user->household->activeMembers()->value('id')]);
});

it('sends the request, queues the emails and goes to the membership page (BR-4.2)', function () {
    Mail::fake();
    $user = customer(2);

    Livewire::actingAs($user)->test(RequestForm::class)
        ->set('packageId', $this->package->id)
        ->set('note', 'Mornings please')
        ->call('send')
        ->assertHasNoErrors()
        ->assertRedirect(route('membership'));

    $membership = Membership::query()->sole();
    expect($membership->household_id)->toBe($user->household->id)
        ->and($membership->monthly_credits)->toBe(16)
        ->and($membership->monthly_price_cents)->toBe(78000)
        ->and($membership->customer_note)->toBe('Mornings please');
    Mail::assertQueued(MembershipRequested::class, 1);
    Mail::assertQueued(AdminMembershipRequest::class, 1);
});

it('needs a package and at least one member', function () {
    Livewire::actingAs(customer())->test(RequestForm::class)
        ->set('packageId', null)
        ->set('memberIds', [])
        ->call('send')
        ->assertHasErrors(['packageId', 'memberIds']);

    expect(Membership::query()->count())->toBe(0);
});

it('asks for an emergency contact first and hides the form (BR-2.6)', function () {
    $user = customer();
    $user->household->update(['emergency_contact_name' => null, 'emergency_contact_phone' => null]);

    Livewire::actingAs($user)->test(RequestForm::class)
        ->assertSee('Add an emergency contact first')
        ->assertSee(route('family'), false)
        ->assertDontSee('Send request');
});

it('shows the pending request instead of the form (BR-4.1)', function () {
    $user = customer();
    Membership::factory()->for($user->household)->for($this->package)->create();

    Livewire::actingAs($user)->test(RequestForm::class)
        ->assertSee('Your request is waiting for approval')
        ->assertSee(route('membership'), false)
        ->assertDontSee('Send request');
});

it('says "Change package or members" while a membership is active (BR-4.7)', function () {
    $user = customer();
    activeMembership($user, '2026-09');

    Livewire::actingAs($user)->test(RequestForm::class)
        ->assertSee('Change package or members')
        ->assertSee('starts on the 1st of next month');
});

it("can't request for someone else's member (BR-2.7)", function () {
    $someoneElse = customer()->household->members->first();

    Livewire::actingAs(customer())->test(RequestForm::class)
        ->set('packageId', $this->package->id)
        ->set('memberIds', [$someoneElse->id])
        ->call('send')
        ->assertHasErrors(['form']);

    expect(Membership::query()->count())->toBe(0);
});

it("can't request an archived package by tampering with the form (BR-3.3)", function () {
    $archived = Package::factory()->inactive()->create();

    Livewire::actingAs(customer())->test(RequestForm::class)
        ->set('packageId', $archived->id)
        ->call('send')
        ->assertHasErrors(['packageId']);

    expect(Membership::query()->count())->toBe(0);
});

it('lets a visitor in only when signed in', function () {
    $this->get('/membership/request')->assertRedirect(route('login'));
});

it('explains that a scheduled package change blocks a new request (BR-4.7)', function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $user = customer();
    activeMembership($user, '2026-09', ['ends_period' => '2026-10']);
    activeMembership($user, '2026-11');

    Livewire::actingAs($user)->test(RequestForm::class)
        ->assertSee('Your package already changes on 1 November')
        ->assertDontSee('Send request');
});
