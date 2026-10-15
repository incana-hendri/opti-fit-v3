<?php

use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelBooking;
use App\Enums\CreditEntryType;
use App\Livewire\Home;
use App\Models\Booking;
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

it('keeps balance = granted - used by counting every positive entry as granted and every negative one as used (BR-5.8)', function () {
    $user = customer();
    $household = $user->household;
    activeMembership($user, '2026-10');
    CreditEntry::factory()->for($household)->create(['amount' => 8]);
    CreditEntry::factory()->for($household)->ofType(CreditEntryType::Adjustment, 2)->create();
    CreditEntry::factory()->for($household)->ofType(CreditEntryType::Adjustment, -1)->create();
    CreditEntry::factory()->for($household)->ofType(CreditEntryType::Booking, -3)->create();
    CreditEntry::factory()->for($household)->ofType(CreditEntryType::Refund, 1)->create();
    CreditEntry::factory()->for($household)->forPeriod('2026-09')->create(['amount' => 20]);

    Livewire::actingAs($user)->test(Home::class)
        ->assertViewHas('balance', 7)
        ->assertViewHas('granted', 11)
        ->assertViewHas('used', 4);
});

it('shows a correct meter for a household with a negative adjustment (BR-5.8)', function () {
    $user = customer();
    activeMembership($user, '2026-10');
    CreditEntry::factory()->for($user->household)->create(['amount' => 24]);
    CreditEntry::factory()->for($user->household)->ofType(CreditEntryType::Adjustment, -1)->create();

    Livewire::actingAs($user)->test(Home::class)
        ->assertSee('23')
        ->assertSee('1 of 24 used this month');
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

// --- Next up and the Book button (docs/04 § /home; M3) ---

it('lists the next three booked classes with member chips and a Book a class button (docs/04)', function () {
    $user = bookingCustomer();
    $household = $user->household;
    $book = fn (string $startsAt, array $members) => app(BookSession::class)->handle(
        $household, classSession(['starts_at' => $startsAt, 'title' => 'Class '.substr($startsAt, 5, 5)]), $members, $user,
    );
    [$mia, $liam] = $household->members->pluck('id')->all();
    $book('2026-10-19 05:00', [$mia, $liam]);
    $book('2026-10-21 05:00', [$mia]);
    $book('2026-10-23 05:00', [$liam]);
    $book('2026-10-26 05:00', [$mia]);

    Livewire::actingAs($user)->test(Home::class)
        ->assertSee('Next up')
        ->assertSee('Class 10-19')
        ->assertSee('Class 10-21')
        ->assertSee('Class 10-23')
        ->assertDontSee('Class 10-26')
        ->assertSee($household->members[0]->first_name)
        ->assertSee(route('bookings'))
        ->assertSee('Book a class')
        ->assertSee(route('book'));
});

it('leaves out cancelled bookings and other households from Next up (BR-2.7)', function () {
    $user = bookingCustomer();
    $other = bookingCustomer();
    $session = classSession(['starts_at' => '2026-10-19 05:00', 'title' => 'Shared class']);
    $mine = app(BookSession::class)->handle($user->household, $session, [$user->household->members->first()->id], $user)->first();
    app(BookSession::class)->handle($other->household, $session, [$other->household->members->first()->id], $other);

    Livewire::actingAs($user)->test(Home::class)
        ->assertSee('Shared class')
        ->assertDontSee($other->household->members->first()->first_name);

    app(CancelBooking::class)->handle($mine, $user);

    Livewire::actingAs($user)->test(Home::class)->assertSee('Nothing booked yet.')->assertDontSee('Shared class');
});

it('hints at covered members with no class booked this week (docs/04)', function () {
    $user = bookingCustomer(3);
    $members = $user->household->members;
    $thisWeek = classSession(['starts_at' => '2026-10-17 09:00']);
    $nextWeek = classSession(['starts_at' => '2026-10-19 05:00']);
    Booking::factory()->for($thisWeek, 'classSession')->create(['member_id' => $members[0]->id]);
    Booking::factory()->for($nextWeek, 'classSession')->create(['member_id' => $members[1]->id]);

    Livewire::actingAs($user)->test(Home::class)
        ->assertSee("{$members[1]->first_name} and {$members[2]->first_name} have no class booked this week.")
        ->assertViewHas('idleMembers', fn ($idle) => $idle->pluck('id')->all() === [$members[1]->id, $members[2]->id]);
});

it('says "has" for one idle member and no hint without a covering package (docs/04)', function () {
    $user = bookingCustomer(1);

    Livewire::actingAs($user)->test(Home::class)->assertSee("{$user->household->members->first()->first_name} has no class booked this week.");
    Livewire::actingAs(customer())->test(Home::class)->assertDontSee('no class booked this week');
});

it('counts a booking in the credit meter\'s used credits (BR-5.8)', function () {
    $user = bookingCustomer();
    app(BookSession::class)->handle($user->household, classSession(['starts_at' => '2026-10-19 05:00']), $user->household->members->pluck('id')->all(), $user);

    Livewire::actingAs($user)->test(Home::class)
        ->assertViewHas('balance', 6)
        ->assertViewHas('used', 2)
        ->assertSee('2 of 8 used this month');
});
