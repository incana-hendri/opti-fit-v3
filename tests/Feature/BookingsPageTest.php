<?php

use App\Actions\Bookings\AdminBook;
use App\Actions\Bookings\BookSession;
use App\Actions\Sessions\CancelSession;
use App\Enums\BookingStatus;
use App\Livewire\BookingsPage;
use App\Models\Booking;
use App\Support\CreditLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

// Friday 16 Oct 2026, 10:00. The Monday 19 Oct 05:00 class has its cutoff on Sunday 18 Oct at 21:00.
beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $this->user = bookingCustomer();
    $this->household = $this->user->household;
    $this->session = classSession(['starts_at' => '2026-10-19 05:00', 'title' => 'Morning Functional Fitness']);
    $this->bookings = app(BookSession::class)->handle($this->household, $this->session, $this->household->members->pluck('id')->all(), $this->user);
});

it('groups upcoming bookings by session with a cancel action per member (docs/04)', function () {
    $other = classSession(['starts_at' => '2026-10-21 16:00', 'title' => 'Afternoon']);
    app(BookSession::class)->handle($this->household, $other, [$this->household->members[0]->id], $this->user);

    $page = Livewire::actingAs($this->user)->test(BookingsPage::class)
        ->assertSee('Morning Functional Fitness')
        ->assertSee('Afternoon')
        ->assertSee('Free cancellation until Sun 18 Oct 21:00');

    foreach ($this->household->members as $member) {
        $page->assertSee("Cancel {$member->first_name}'s booking", false);
    }
    $page->assertViewHas('upcoming', fn ($sessions) => $sessions->count() === 2 && $sessions->first()->bookings->count() === 2);
});

it('shows only this household\'s bookings on a shared session (BR-2.7)', function () {
    $stranger = bookingCustomer();
    app(BookSession::class)->handle($stranger->household, $this->session, [$stranger->household->members->first()->id], $stranger);

    Livewire::actingAs($this->user)->test(BookingsPage::class)
        ->assertDontSee($stranger->household->members->first()->first_name)
        ->assertViewHas('upcoming', fn ($sessions) => $sessions->first()->bookings->count() === 2);
});

it('asks for confirmation before the cutoff with the credit-returned wording (docs/04, BR-7.7)', function () {
    $mia = $this->bookings[0];

    Livewire::actingAs($this->user)->test(BookingsPage::class)
        ->call('confirmCancel', $mia->id)
        ->assertDispatched('open-sheet', name: 'cancel-booking')
        ->assertSee("Cancel {$mia->member->first_name}'s booking?")
        ->assertSee('Your credit will be returned.')
        ->assertDontSee('Late cancellation.');
});

it('warns about a late cancellation after the cutoff (docs/04, BR-7.7)', function () {
    $this->travelTo(Carbon::parse('2026-10-18 21:30'));
    $mia = $this->bookings[0];

    Livewire::actingAs($this->user)->test(BookingsPage::class)
        ->assertSee('Free cancellation has closed.')
        ->call('confirmCancel', $mia->id)
        ->assertSee('Late cancellation.')
        ->assertSee("{$mia->member->first_name}")
        ->assertSee("'s spot will be freed, but the credit won't be returned.", false)
        ->assertDontSee('Your credit will be returned.');
});

it('cancels with a refund before the cutoff and tells the customer (BR-7.7)', function () {
    $mia = $this->bookings[0];

    Livewire::actingAs($this->user)->test(BookingsPage::class)
        ->call('confirmCancel', $mia->id)
        ->call('cancel')
        ->assertDispatched('toast', message: "{$mia->member->first_name}'s booking is cancelled. Your credit has been returned.")
        ->assertDispatched('close-sheet', name: 'cancel-booking')
        ->assertSet('cancelId', null);

    expect($mia->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and(CreditLedger::balance($this->household))->toBe(7);
});

it('cancels late without a refund after the cutoff (BR-7.7)', function () {
    $this->travelTo(Carbon::parse('2026-10-18 21:30'));
    $mia = $this->bookings[0];

    Livewire::actingAs($this->user)->test(BookingsPage::class)
        ->call('confirmCancel', $mia->id)
        ->call('cancel')
        ->assertDispatched('toast', message: "{$mia->member->first_name}'s booking is cancelled.");

    expect($mia->fresh()->late_cancellation)->toBeTrue()
        ->and(CreditLedger::balance($this->household))->toBe(6);
});

it('can\'t cancel another household\'s booking (BR-2.7)', function () {
    $stranger = bookingCustomer();
    $theirs = app(BookSession::class)->handle($stranger->household, $this->session, [$stranger->household->members->first()->id], $stranger)->first();

    Livewire::actingAs($this->user)->test(BookingsPage::class)
        ->call('confirmCancel', $theirs->id)
        ->assertNotFound();

    expect($theirs->fresh()->status)->toBe(BookingStatus::Booked);
});

it('can\'t be pointed at another household\'s booking from the browser (BR-2.7)', function () {
    $stranger = bookingCustomer();
    $theirs = app(BookSession::class)->handle($stranger->household, $this->session, [$stranger->household->members->first()->id], $stranger)->first();

    $page = Livewire::actingAs($this->user)->test(BookingsPage::class)->call('confirmCancel', $this->bookings[0]->id);

    expect(fn () => $page->set('cancelId', $theirs->id))->toThrow(CannotUpdateLockedPropertyException::class);
    expect($theirs->fresh()->status)->toBe(BookingStatus::Booked);
});

it('shows an empty state with a way to book (docs/04)', function () {
    $empty = bookingCustomer();

    Livewire::actingAs($empty)->test(BookingsPage::class)
        ->assertSee('Nothing booked yet')
        ->assertSee(route('book'));
});

it('lists the last 60 days in Past with the right status labels (docs/04)', function () {
    $this->travelTo(Carbon::parse('2026-10-30 10:00'));
    [$attended, $noShow, $cancelled, $late, $byAdmin, $studio, $old, $unmarkedRecent, $unmarkedOld] = array_map(
        fn () => classSession(['starts_at' => '2026-10-20 05:00']),
        range(1, 9),
    );
    $members = fn () => $this->household->members;

    $make = fn ($session, $member, array $state = []) => Booking::factory()->for($session, 'classSession')->state($state)->create(['member_id' => $member->id]);
    $make($attended, $members()[0], ['status' => BookingStatus::Attended]);
    $make($noShow, $members()[0], ['status' => BookingStatus::NoShow]);
    $make($cancelled, $members()[0], ['status' => BookingStatus::Cancelled, 'cancelled_by' => $this->user->id]);
    $make($late, $members()[0], ['status' => BookingStatus::Cancelled, 'late_cancellation' => true, 'cancelled_by' => $this->user->id]);
    $make($byAdmin, $members()[0], ['status' => BookingStatus::Cancelled, 'cancelled_by' => admin()->id]);
    $make($studio, $members()[0], ['status' => BookingStatus::Cancelled, 'cancelled_by' => $this->user->id]);
    $studio->update(['status' => 'cancelled']);
    $make($old, $members()[0], ['status' => BookingStatus::Attended]);
    $old->update(['starts_at' => '2026-08-01 05:00']);
    $make($unmarkedOld, $members()[0]);
    $unmarkedOld->update(['starts_at' => '2026-10-28 05:00']);
    $make($unmarkedRecent, $members()[0]);
    $unmarkedRecent->update(['starts_at' => '2026-10-30 05:00']);

    $page = Livewire::actingAs($this->user)->test(BookingsPage::class)->set('tab', 'past');

    $page->assertSee('Attended')->assertSee('No-show')->assertSee('Late cancel')->assertSee('Cancelled by studio')->assertSee('Cancelled')
        ->assertViewHas('past', fn ($sessions) => $sessions->count() === 9 && ! $sessions->contains($old))
        ->assertDontSee('Nothing booked yet');

    $labels = fn () => $page->viewData('past')->flatMap->bookings->map(fn ($booking) => $booking->customerLabel()[0])->sort()->values()->all();
    expect($labels())->toBe(['Attended', 'Attended', 'Attended', 'Attended', 'Booked', 'Cancelled', 'Cancelled by studio', 'Cancelled by studio', 'Late cancel', 'No-show']);
});

it('moves a booking to Past once the class has started and keeps the upcoming ones apart (docs/04)', function () {
    $this->travelTo(Carbon::parse('2026-10-19 06:00'));

    Livewire::actingAs($this->user)->test(BookingsPage::class)
        ->assertViewHas('upcoming', fn ($sessions) => $sessions->isEmpty())
        ->assertViewHas('past', fn ($sessions) => $sessions->count() === 1);
});

it('shows a studio-cancelled session under Past with the studio label (BR-7.8)', function () {
    app(CancelSession::class)->handle($this->session, 'Storm', admin());

    $page = Livewire::actingAs($this->user)->test(BookingsPage::class)->assertViewHas('upcoming', fn ($sessions) => $sessions->isEmpty());

    expect($page->viewData('past')->first()->bookings->map(fn ($booking) => $booking->customerLabel()[0])->unique()->all())->toBe(['Cancelled by studio']);
});

it('shows an admin-booked free booking and lets the customer cancel it without a refund (BR-6.3)', function () {
    $free = app(AdminBook::class)->handle($this->household, classSession(['starts_at' => '2026-10-20 05:00']), [$this->household->members[0]->id], false, false, admin())->first();

    Livewire::actingAs($this->user)->test(BookingsPage::class)
        ->call('confirmCancel', $free->id)
        ->assertDontSee('Your credit will be returned.')
        ->call('cancel');

    expect(CreditLedger::balance($this->household))->toBe(6);
});

it('shows the Book page link in the page navigation and renders for a member-less state', function () {
    $this->actingAs($this->user)->get('/bookings')->assertOk()->assertSee('Upcoming')->assertSee('Past');
});
