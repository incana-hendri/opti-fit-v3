<?php

use App\Actions\Bookings\BookSession;
use App\Actions\Sessions\GenerateSessions;
use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use App\Filament\Resources\ClassSessions\Pages\CreateClassSession;
use App\Filament\Resources\ClassSessions\Pages\EditClassSession;
use App\Filament\Resources\ClassSessions\Pages\ListClassSessions;
use App\Filament\Resources\ClassSessions\Pages\ViewClassSession;
use App\Filament\Resources\ClassSessions\RelationManagers\BookingsRelationManager;
use App\Filament\Resources\ClassSessions\RelationManagers\GuestsRelationManager;
use App\Filament\Resources\Households\Pages\ViewHousehold;
use App\Filament\Resources\Households\RelationManagers\BookingsRelationManager as HouseholdBookingsRelationManager;
use App\Mail\BookingCancelledByStudio;
use App\Mail\BookingConfirmed;
use App\Mail\SessionCancelled;
use App\Mail\SessionTimeChanged;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditEntry;
use App\Models\GuestEntry;
use App\Models\Location;
use App\Models\Member;
use App\Models\ScheduleTemplate;
use App\Support\CreditLedger;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $this->admin = admin();
    $this->actingAs($this->admin);
});

function rosterOf(ClassSession $session)
{
    return Livewire::test(BookingsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewClassSession::class]);
}

/**
 * A session on Monday 19 Oct 05:00 that the customer has booked all members into.
 */
function bookedClass(array $attributes = [], int $members = 2): array
{
    Mail::fake();
    $user = bookingCustomer($members);
    $session = classSession($attributes + ['starts_at' => '2026-10-19 05:00']);
    $bookings = app(BookSession::class)->handle($user->household, $session, $user->household->members->pluck('id')->all(), $user);
    Mail::fake();

    return [$session, $user, $bookings];
}

// --- Smoke and lists (docs/05, docs/09) ---

it('renders the admin index, create, edit and roster pages', function () {
    [$session] = bookedClass();

    $this->get('/admin/class-sessions')->assertOk();
    $this->get('/admin/class-sessions/create')->assertOk();
    $this->get("/admin/class-sessions/{$session->id}")->assertOk()->assertSee('Booking closes')->assertSee('Mon 19 Oct, 05:00');
    $this->get("/admin/class-sessions/{$session->id}/edit")->assertOk();
    $this->get('/admin/locations')->assertOk();
    $this->get('/admin/schedule-templates')->assertOk();
    $this->get('/admin/schedule-templates/create')->assertOk();
});

it('lists the next 14 days by default, earliest first, and the past on its own tab (docs/05)', function () {
    $soon = classSession(['starts_at' => '2026-10-17 05:00']);
    $later = classSession(['starts_at' => '2026-10-20 05:00']);
    $far = classSession(['starts_at' => '2026-11-20 05:00']);
    $yesterday = classSession(['starts_at' => '2026-10-15 05:00']);
    $older = classSession(['starts_at' => '2026-10-01 05:00']);

    Livewire::test(ListClassSessions::class)
        ->assertCanSeeTableRecords([$soon, $later], inOrder: true)
        ->assertCanNotSeeTableRecords([$far, $yesterday, $older])
        ->set('activeTab', 'past')
        ->assertCanSeeTableRecords([$yesterday, $older], inOrder: true)
        ->assertCanNotSeeTableRecords([$soon, $far])
        ->set('activeTab', 'upcoming')
        ->assertCanSeeTableRecords([$soon, $later])
        ->assertCanNotSeeTableRecords([$far]);
});

it('filters by status and venue (docs/05)', function () {
    $scheduled = classSession(['starts_at' => '2026-10-17 05:00']);
    $cancelled = classSession(['starts_at' => '2026-10-18 05:00', 'status' => SessionStatus::Cancelled]);
    $other = classSession(['starts_at' => '2026-10-19 05:00', 'location_id' => Location::factory()->create()->id]);

    Livewire::test(ListClassSessions::class)
        ->filterTable('status', 'cancelled')
        ->assertCanSeeTableRecords([$cancelled])
        ->assertCanNotSeeTableRecords([$scheduled, $other])
        ->resetTableFilters()
        ->filterTable('location_id', $other->location_id)
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$scheduled, $cancelled]);
});

it('shows booked over capacity with guests (docs/05)', function () {
    [$session] = bookedClass(['capacity' => 10]);
    GuestEntry::factory()->for($session)->create(['participant_count' => 2]);

    Livewire::test(ListClassSessions::class)
        ->assertTableColumnStateSet('places', '2/10', $session)
        ->assertSee('+2 guests');
});

// --- Create, edit, reschedule (BR-7.1, 7.10) ---

it('creates a session with the BR-7.1 fields', function () {
    $location = Location::query()->sole();

    Livewire::test(CreateClassSession::class)
        ->fillForm([
            'title' => 'Special Saturday', 'location_id' => $location->id, 'starts_at' => '2026-10-24 08:00',
            'duration_minutes' => 60, 'capacity' => 20, 'min_participants' => 4, 'cutoff_hours' => 12, 'description' => 'Bring a friend',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $session = ClassSession::query()->where('title', 'Special Saturday')->sole();
    expect($session->starts_at->format('Y-m-d H:i'))->toBe('2026-10-24 08:00')
        ->and($session->status)->toBe(SessionStatus::Scheduled)
        ->and($session->cutoff_hours)->toBe(12)
        ->and($session->schedule_template_id)->toBeNull();
});

it('asks before a time change and says how many households will be emailed (BR-7.10)', function () {
    [$session] = bookedClass();

    Livewire::test(EditClassSession::class, ['record' => $session->id])
        ->fillForm(['starts_at' => '2026-10-19 06:00'])
        ->call('save')
        ->assertActionMounted('confirmTimeChange')
        ->assertMountedActionModalSee('1 household will be emailed');

    expect($session->fresh()->starts_at->format('H:i'))->toBe('05:00');
});

it('changes the time after confirmation and queues C12 (BR-7.10)', function () {
    [$session, $user] = bookedClass();

    Livewire::test(EditClassSession::class, ['record' => $session->id])
        ->fillForm(['starts_at' => '2026-10-19 06:00'])
        ->call('save')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($session->fresh()->starts_at->format('H:i'))->toBe('06:00');
    Mail::assertQueued(SessionTimeChanged::class, fn (SessionTimeChanged $mail) => $mail->hasTo($user->email));
});

it('saves a capacity change straight away without touching bookings or emailing anyone (BR-7.10)', function () {
    [$session] = bookedClass();

    Livewire::test(EditClassSession::class, ['record' => $session->id])
        ->fillForm(['capacity' => 1])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertActionNotMounted('confirmTimeChange');

    expect($session->fresh()->capacity)->toBe(1)
        ->and(Booking::query()->where('status', BookingStatus::Booked)->count())->toBe(2);
    Mail::assertNothingQueued();
});

// --- Row actions (docs/05) ---

it('duplicates a session to another date without bookings (docs/05)', function () {
    [$session] = bookedClass(['title' => 'Boot camp', 'capacity' => 9, 'cutoff_hours' => 6, 'description' => 'Bring water']);
    $session->update(['schedule_template_id' => ScheduleTemplate::query()->value('id')]);

    Livewire::test(ListClassSessions::class)
        ->callAction(TestAction::make('duplicate')->table($session), ['date' => '2026-10-26'])
        ->assertNotified();

    $copy = ClassSession::query()->where('id', '!=', $session->id)->where('title', 'Boot camp')->sole();
    expect($copy->starts_at->format('Y-m-d H:i'))->toBe('2026-10-26 05:00')
        ->and($copy->capacity)->toBe(9)
        ->and($copy->cutoff_hours)->toBe(6)
        ->and($copy->description)->toBe('Bring water')
        ->and($copy->status)->toBe(SessionStatus::Scheduled)
        ->and($copy->schedule_template_id)->toBeNull()
        ->and($copy->bookings()->count())->toBe(0);
});

it('cancels a session with a reason, refunds and emails (BR-7.8)', function () {
    [$session, $user] = bookedClass();

    Livewire::test(ListClassSessions::class)
        ->mountAction(TestAction::make('cancelSession')->table($session))
        ->assertMountedActionModalSee('2 bookings will be cancelled and their credits returned. 1 household will be emailed.')
        ->setActionData(['reason' => 'The coach is ill.'])
        ->callMountedAction()
        ->assertNotified('Session cancelled');

    expect($session->fresh()->status)->toBe(SessionStatus::Cancelled)
        ->and($session->fresh()->cancellation_reason)->toBe('The coach is ill.')
        ->and(CreditLedger::balance($user->household))->toBe(8);
    Mail::assertQueued(SessionCancelled::class, fn ($mail) => $mail->hasTo($user->email));
});

it('hides Cancel session once a session is cancelled', function () {
    $cancelled = classSession(['starts_at' => '2026-10-18 05:00', 'status' => SessionStatus::Cancelled]);

    Livewire::test(ListClassSessions::class)
        ->assertActionHidden(TestAction::make('cancelSession')->table($cancelled));
});

// --- Roster (docs/05) ---

it('shows the roster with member, household, status, credit and health notes (docs/05)', function () {
    [$session, $user, $bookings] = bookedClass();
    $mia = $bookings[0]->member;
    $mia->update(['health_notes' => 'Bad left knee']);

    rosterOf($session)
        ->assertCanSeeTableRecords($bookings)
        ->assertTableColumnStateSet('age', $mia->age(), $bookings[0])
        ->assertSee($user->household->name)
        ->assertSee('Booked')
        ->assertTableColumnStateSet('health_notes', true, $bookings[0])
        ->assertTableColumnStateSet('health_notes', false, $bookings[1]);
});

it('hides cancelled bookings by default and can show them (docs/05)', function () {
    [$session, , $bookings] = bookedClass();
    $bookings[0]->update(['status' => BookingStatus::Cancelled]);

    rosterOf($session)
        ->assertCanSeeTableRecords([$bookings[1]])
        ->assertCanNotSeeTableRecords([$bookings[0]])
        ->removeTableFilter('hideCancelled')
        ->assertCanSeeTableRecords([$bookings[0], $bookings[1]]);
});

it('marks attendance from the roster (BR-7.9)', function () {
    [$session, , $bookings] = bookedClass();
    $this->travelTo(Carbon::parse('2026-10-19 05:00'));

    rosterOf($session)
        ->callAction(TestAction::make('markAttended')->table($bookings[0]))
        ->assertNotified('Marked attended')
        ->callAction(TestAction::make('markNoShow')->table($bookings[1]))
        ->assertNotified('Marked no-show');

    expect($bookings[0]->fresh()->status)->toBe(BookingStatus::Attended)
        ->and($bookings[1]->fresh()->status)->toBe(BookingStatus::NoShow);
});

it('refuses to mark attendance too early and says why (BR-7.9)', function () {
    [$session, , $bookings] = bookedClass();

    rosterOf($session)
        ->callAction(TestAction::make('markAttended')->table($bookings[0]))
        ->assertNotified('Attendance can be marked from 30 minutes before the class starts.');

    expect($bookings[0]->fresh()->status)->toBe(BookingStatus::Booked);
});

it('marks selected bookings attended with the bulk action (docs/05)', function () {
    [$session, , $bookings] = bookedClass();
    $this->travelTo(Carbon::parse('2026-10-19 05:00'));

    rosterOf($session)
        ->selectTableRecords([$bookings[0]->id, $bookings[1]->id])
        ->callAction(TestAction::make('markSelectedAttended')->table()->bulk())
        ->assertNotified('Marked 2 attended');

    expect(Booking::query()->where('status', BookingStatus::Attended)->count())->toBe(2);
});

it('cancels from the roster with the return-credit default per BR-6.2', function () {
    [$session, $user, $bookings] = bookedClass();

    $page = rosterOf($session)->mountAction(TestAction::make('cancel')->table($bookings[0]))->assertActionDataSet(['return_credit' => true]);
    $page->callMountedAction()->assertNotified('Booking cancelled');

    expect(CreditLedger::balance($user->household))->toBe(7);
    Mail::assertQueued(BookingCancelledByStudio::class, fn ($mail) => $mail->creditReturned);

    $this->travelTo(Carbon::parse('2026-10-18 22:00'));
    rosterOf($session)
        ->mountAction(TestAction::make('cancel')->table($bookings[1]))
        ->assertActionDataSet(['return_credit' => false])
        ->callMountedAction();

    expect(CreditLedger::balance($user->household))->toBe(7);
});

it('cancels with the credit returned when the admin switches it on after the cutoff (BR-6.2)', function () {
    [$session, $user, $bookings] = bookedClass();
    $this->travelTo(Carbon::parse('2026-10-18 22:00'));

    rosterOf($session)->callAction(TestAction::make('cancel')->table($bookings[0]), ['return_credit' => true]);

    expect(CreditLedger::balance($user->household))->toBe(7);
});

it('returns a credit from the roster only when it is eligible (BR-6.1)', function () {
    [$session, $user, $bookings] = bookedClass();
    $this->travelTo(Carbon::parse('2026-10-19 06:00'));
    $bookings[0]->update(['status' => BookingStatus::NoShow]);

    rosterOf($session)
        ->assertActionHidden(TestAction::make('returnCredit')->table($bookings[1]))
        ->assertActionVisible(TestAction::make('returnCredit')->table($bookings[0]))
        ->callAction(TestAction::make('returnCredit')->table($bookings[0]))
        ->assertNotified('Credit returned')
        ->assertActionHidden(TestAction::make('returnCredit')->table($bookings[0]));

    expect(CreditLedger::balance($user->household))->toBe(7);
});

// --- Add booking (BR-6.3, 6.4) ---

it('books a household\'s members from the roster header, charging a credit each (BR-6.3)', function () {
    $user = bookingCustomer(3);
    $session = classSession(['starts_at' => '2026-10-19 05:00']);
    $members = $user->household->members;
    Mail::fake();

    Livewire::test(ViewClassSession::class, ['record' => $session->id])
        ->callAction('addBooking', ['household_id' => $user->household->id, 'member_ids' => [$members[0]->id, $members[2]->id], 'charge' => true, 'over_capacity' => false])
        ->assertNotified('Booked 2 people');

    expect(Booking::query()->count())->toBe(2)
        ->and(CreditLedger::balance($user->household))->toBe(6)
        ->and(Booking::query()->first()->booked_by)->toBe($this->admin->id);
    Mail::assertQueued(BookingConfirmed::class, 1);
});

it('lets the admin book uncovered members and make the booking free (BR-6.3, docs/05)', function () {
    $user = bookingCustomer(1);
    $extra = Member::factory()->for($user->household)->create();
    $session = classSession(['starts_at' => '2026-10-19 05:00']);

    Livewire::test(ViewClassSession::class, ['record' => $session->id])
        ->mountAction('addBooking')
        ->setActionData(['household_id' => $user->household->id])
        ->assertSchemaComponentExists('member_ids')
        ->setActionData(['household_id' => $user->household->id, 'member_ids' => [$extra->id], 'charge' => false])
        ->callMountedAction()
        ->assertNotified('Booked 1 person');

    expect(Booking::query()->sole()->credit_charged)->toBeFalse()
        ->and(CreditLedger::balance($user->household))->toBe(8);
});

it('needs the over-capacity box when the class is full (BR-6.4)', function () {
    $user = bookingCustomer(2);
    $session = classSession(['starts_at' => '2026-10-19 05:00', 'capacity' => 1]);
    GuestEntry::factory()->for($session)->create();
    $data = ['household_id' => $user->household->id, 'member_ids' => [$user->household->members->first()->id], 'charge' => true];

    Livewire::test(ViewClassSession::class, ['record' => $session->id])
        ->callAction('addBooking', $data + ['over_capacity' => false])
        ->assertHasActionErrors(['over_capacity']);
    expect(Booking::query()->count())->toBe(0);

    Livewire::test(ViewClassSession::class, ['record' => $session->id])
        ->callAction('addBooking', $data + ['over_capacity' => true])
        ->assertHasNoActionErrors();
    expect(Booking::query()->count())->toBe(1)
        ->and($session->fresh()->placesLeft())->toBe(-1);
});

it('shows a clear error when the household has too few credits for an admin booking (BR-6.3)', function () {
    $user = bookingCustomer(2, 0);
    $session = classSession(['starts_at' => '2026-10-19 05:00']);

    Livewire::test(ViewClassSession::class, ['record' => $session->id])
        ->callAction('addBooking', ['household_id' => $user->household->id, 'member_ids' => [$user->household->members->first()->id], 'charge' => true, 'over_capacity' => false])
        ->assertNotified();

    expect(Booking::query()->count())->toBe(0);
});

it('hides Add booking on a cancelled session', function () {
    $session = classSession(['starts_at' => '2026-10-19 05:00', 'status' => SessionStatus::Cancelled]);

    Livewire::test(ViewClassSession::class, ['record' => $session->id])->assertActionHidden('addBooking');
});

// --- Guests (BR-9.3) ---

it('adds and removes guests, who take places but no credits (BR-9.3)', function () {
    $session = classSession(['starts_at' => '2026-10-19 05:00', 'capacity' => 5]);

    $page = Livewire::test(GuestsRelationManager::class, ['ownerRecord' => $session, 'pageClass' => ViewClassSession::class])
        ->callAction(TestAction::make('create')->table(), ['name' => 'Trial: Pieter', 'participant_count' => 2, 'note' => 'Friend of the Smiths'])
        ->assertHasNoFormErrors();

    $guest = GuestEntry::query()->sole();
    expect($guest->created_by)->toBe($this->admin->id)
        ->and($session->fresh()->placesLeft())->toBe(3)
        ->and(CreditEntry::query()->count())->toBe(0);

    $page->callAction(TestAction::make('delete')->table($guest));
    expect(GuestEntry::query()->count())->toBe(0)
        ->and($session->fresh()->placesLeft())->toBe(5);
});

// --- Households Bookings tab (docs/05) ---

it('shows a household\'s upcoming and past bookings with the same row actions (docs/05)', function () {
    [$session, $user, $bookings] = bookedClass();
    $pastSession = classSession(['starts_at' => '2026-10-12 05:00']);
    $past = Booking::factory()->for($pastSession, 'classSession')->noShow()->create(['member_id' => $user->household->members->first()->id]);

    $tab = fn (string $name) => Livewire::test(HouseholdBookingsRelationManager::class, ['ownerRecord' => $user->household, 'pageClass' => ViewHousehold::class])
        ->set('activeTab', $name);

    $tab('upcoming')->assertCanSeeTableRecords($bookings)->assertCanNotSeeTableRecords([$past]);
    $tab('past')->assertCanSeeTableRecords([$past])->assertCanNotSeeTableRecords($bookings)
        ->callAction(TestAction::make('returnCredit')->table($past))
        ->assertNotified('Credit returned');
    $tab('upcoming')->callAction(TestAction::make('cancel')->table($bookings[0]), ['return_credit' => true])->assertNotified('Booking cancelled');

    expect(CreditLedger::balance($user->household))->toBe(8)
        ->and($bookings[0]->fresh()->status)->toBe(BookingStatus::Cancelled);
});

it('shows the Bookings tab on the household page', function () {
    $user = bookingCustomer();

    $this->get("/admin/households/{$user->household->id}")->assertOk();
});

// --- Verifier fixes ---

it('opens on the Past tab with no date filter when the URL says so', function () {
    $yesterday = classSession(['starts_at' => '2026-10-15 05:00']);
    $soon = classSession(['starts_at' => '2026-10-17 05:00']);

    Livewire::withQueryParams(['tab' => 'past'])->test(ListClassSessions::class)
        ->assertCanSeeTableRecords([$yesterday])
        ->assertCanNotSeeTableRecords([$soon]);
});

it('refuses to move a session onto a slot of the same template with a clear message (BR-8.2)', function () {
    ScheduleTemplate::query()->delete();
    $template = ScheduleTemplate::factory()->on(1, '05:00:00')->create();
    app(GenerateSessions::class)->handle(collect([$template]), Carbon::parse('2026-10-19'), Carbon::parse('2026-10-26'));
    [$first, $second] = ClassSession::query()->orderBy('starts_at')->get()->all();

    Livewire::test(EditClassSession::class, ['record' => $second->id])
        ->fillForm(['starts_at' => $first->starts_at->format('Y-m-d H:i:s')])
        ->call('save')
        ->callMountedAction()
        ->assertNotified('Another class from the same template already starts at that time.');

    expect($second->fresh()->starts_at->format('Y-m-d H:i'))->toBe('2026-10-26 05:00');
});

it('still lets a session whose venue was archived be edited (docs/05)', function () {
    $location = Location::factory()->inactive()->create();
    $session = classSession(['starts_at' => '2026-10-19 05:00', 'location_id' => $location->id]);

    Livewire::test(EditClassSession::class, ['record' => $session->id])
        ->fillForm(['capacity' => 12])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($session->fresh()->capacity)->toBe(12)->and($session->fresh()->location_id)->toBe($location->id);
});
