<?php

use App\Enums\BookingStatus;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Filament\Resources\ClassSessions\Pages\ListClassSessions;
use App\Filament\Resources\ClassSessions\Pages\ViewClassSession;
use App\Filament\Resources\ClassSessions\RelationManagers\BookingsRelationManager;
use App\Filament\Resources\ClassSessions\RelationManagers\GuestsRelationManager;
use App\Filament\Resources\Households\Pages\ViewHousehold;
use App\Filament\Resources\Households\RelationManagers\BookingsRelationManager as HouseholdBookingsRelationManager;
use App\Filament\Resources\Households\RelationManagers\CreditsRelationManager;
use App\Filament\Resources\Households\RelationManagers\MembershipsRelationManager;
use App\Filament\Resources\Households\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Memberships\Pages\ListMemberships;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditEntry;
use App\Models\GuestEntry;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Package;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// Admins see the result of an action straight away, wherever it shows: after any action the page and its relation
// managers all refresh (the shared `refresh-page` event, AppServiceProvider).
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $this->actingAs(admin());
});

/**
 * @return array{ClassSession, Booking}
 */
function rosterWithOneBooking(): array
{
    $session = classSession(['starts_at' => '2026-10-19 05:00', 'capacity' => 10, 'min_participants' => 2]);
    $booking = Booking::factory()->for($session, 'classSession')->create();

    return [$session, $booking];
}

it('refreshes the roster\'s tables when a header action on the page adds a booking', function () {
    [$session] = rosterWithOneBooking();
    $user = bookingCustomer();
    $member = $user->household->members->first();

    Livewire::test(ViewClassSession::class, ['record' => $session->id])
        ->callAction('addBooking', ['household_id' => $user->household->id, 'member_ids' => [$member->id], 'charge' => true, 'over_capacity' => false])
        ->assertDispatched('refresh-page');
});

it('re-renders the Bookings table when the page tells it to, showing a booking added elsewhere', function () {
    [$session] = rosterWithOneBooking();
    $table = Livewire::test(BookingsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewClassSession::class]);
    $newcomer = Booking::factory()->for($session, 'classSession')->create();

    $table->assertDontSee($newcomer->member->first_name)
        ->dispatch('refresh-page')
        ->assertSee($newcomer->member->first_name);
});

it('re-renders the Guests table when the page tells it to', function () {
    [$session] = rosterWithOneBooking();
    $table = Livewire::test(GuestsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewClassSession::class]);
    GuestEntry::factory()->for($session)->create(['name' => 'Holiday group']);

    $table->dispatch('refresh-page')->assertSee('Holiday group');
});

it('updates the roster\'s places left and minimum when an action in a table changes them', function () {
    [$session, $booking] = rosterWithOneBooking();
    $page = Livewire::test(ViewClassSession::class, ['record' => $session->id])->assertSee('9 of 10')->assertSee('Below minimum (1 of 2)');

    Booking::factory()->for($session, 'classSession')->create();
    $page->dispatch('refresh-page')->assertSee('8 of 10')->assertSee('Minimum met (2 of 2)');

    Livewire::test(BookingsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewClassSession::class])
        ->callAction(TestAction::make('cancel')->table($booking), ['return_credit' => false])
        ->assertDispatched('refresh-page');
    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);

    $page->dispatch('refresh-page')->assertSee('9 of 10')->assertSee('Below minimum (1 of 2)');
});

it('tells the page after every roster table action', function (string $action, array $data) {
    [$session, $booking] = rosterWithOneBooking();
    $this->travelTo(Carbon::parse('2026-10-19 05:00'));
    $table = Livewire::test(BookingsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewClassSession::class]);

    $table->callAction(TestAction::make($action)->table($booking), $data)->assertDispatched('refresh-page');
})->with([
    'mark attended' => ['markAttended', []],
    'mark no-show' => ['markNoShow', []],
    'cancel' => ['cancel', ['return_credit' => true]],
]);

it('tells the page after the bulk attendance action', function () {
    [$session, $booking] = rosterWithOneBooking();
    $this->travelTo(Carbon::parse('2026-10-19 05:00'));

    Livewire::test(BookingsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewClassSession::class])
        ->selectTableRecords([$booking->id])
        ->callAction(TestAction::make('markSelectedAttended')->table()->bulk())
        ->assertNotified('Marked 1 attended')
        ->assertDispatched('refresh-page');
});

it('tells the page after guests are added and removed', function () {
    [$session] = rosterWithOneBooking();
    $guests = Livewire::test(GuestsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewClassSession::class]);

    $guests->callAction(TestAction::make('create')->table(), ['name' => 'Trial', 'participant_count' => 2])->assertDispatched('refresh-page');
    $guest = $session->guestEntries()->firstOrFail();
    $guests->callAction(TestAction::make('delete')->table($guest))->assertDispatched('refresh-page');
});

it('refreshes the household page\'s other tabs when one tab\'s action changes data', function (string $manager, Closure $changeBehindItsBack, string $expected) {
    $user = customer(2);
    activeMembership($user);
    $household = $user->household;
    $table = Livewire::test($manager, ['ownerRecord' => $household, 'pageClass' => ViewHousehold::class]);
    $changeBehindItsBack($household);

    $table->dispatch('refresh-page')->assertSee($expected);
})->with([
    'credits' => [CreditsRelationManager::class, fn ($household) => CreditEntry::factory()->for($household)->create(['description' => 'Goodwill credit']), 'Goodwill credit'],
    'members' => [MembersRelationManager::class, fn ($household) => Member::factory()->for($household)->create(['first_name' => 'Newcomer']), 'Newcomer'],
    'memberships' => [MembershipsRelationManager::class, fn ($household) => Membership::factory()->for($household)->declined()->create(), 'Declined'],
    'bookings' => [HouseholdBookingsRelationManager::class, function ($household) {
        Booking::factory()->for($household)->for($household->members->first(), 'member')->for(classSession(['title' => 'Late Addition', 'starts_at' => '2026-10-19 05:00']), 'classSession')->create();
    }, 'Late Addition'],
]);

it('refreshes the household balance and the other tabs after adjusting credits and after a booking action', function () {
    $user = bookingCustomer(1, 4);
    $household = $user->household;
    $session = classSession(['starts_at' => '2026-10-19 05:00']);
    $booking = Booking::factory()->for($household)->for($household->members->first(), 'member')->for($session, 'classSession')->noShow()->create();

    Livewire::test(CreditsRelationManager::class, ['ownerRecord' => $household, 'pageClass' => ViewHousehold::class])
        ->callAction(TestAction::make('adjust')->table(), ['amount' => 2, 'reason' => 'Goodwill'])
        ->assertDispatched('refresh-page')
        ->assertSee('Balance for October: 6');

    Livewire::test(HouseholdBookingsRelationManager::class, ['ownerRecord' => $household, 'pageClass' => ViewHousehold::class])
        ->callAction(TestAction::make('returnCredit')->table($booking))
        ->assertDispatched('refresh-page');
});

it('tells the other components after a household page action and a list action', function () {
    $household = customer()->household;

    Livewire::test(ViewHousehold::class, ['record' => $household->getRouteKey()])
        ->callAction('deactivate')
        ->assertDispatched('refresh-page')
        ->assertActionHidden('deactivate')
        ->assertActionVisible('reactivate');

    $request = Membership::factory()->create();
    Livewire::test(ListMemberships::class)
        ->callAction(TestAction::make('decline')->table($request), ['reason' => 'Full'])
        ->assertDispatched('refresh-page');
});

it('shows the result at once on the list pages that run their own actions', function () {
    // A table re-renders after its own row actions: the request leaves the Requested tab, the badge flips.
    $request = Membership::factory()->create();
    Livewire::test(ListMemberships::class)
        ->assertCanSeeTableRecords([$request])
        ->callAction(TestAction::make('decline')->table($request), ['reason' => 'Full'])
        ->assertCanNotSeeTableRecords([$request]);

    $other = admin();
    Livewire::test(ListAdmins::class)
        ->assertDontSee('Deactivated')
        ->callAction(TestAction::make('deactivate')->table($other))
        ->assertSee('Deactivated')
        ->assertActionHidden(TestAction::make('deactivate')->table($other))
        ->assertActionVisible(TestAction::make('reactivate')->table($other));

    $package = Package::factory()->create();
    Livewire::test(ListPackages::class)
        ->callAction(TestAction::make('archive')->table($package))
        ->assertActionHidden(TestAction::make('archive')->table($package))
        ->assertActionVisible(TestAction::make('unarchive')->table($package));

    $session = classSession(['starts_at' => '2026-10-20 05:00']);
    Livewire::test(ListClassSessions::class)
        ->callAction(TestAction::make('cancelSession')->table($session), ['reason' => 'Coach ill'])
        ->assertDispatched('refresh-page')
        ->assertSee('Cancelled');
});
