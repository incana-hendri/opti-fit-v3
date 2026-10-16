<?php

use App\Actions\Bookings\BookSession;
use App\Livewire\BookingsPage;
use App\Livewire\BookPage;
use App\Livewire\Home;
use App\Livewire\MembershipPage;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// docs/04 § Empty states, errors, feedback: every list has a friendly empty state with one next action.
it('invites a customer without bookings to book a class from Home', function () {
    $user = bookingCustomer();

    Livewire::actingAs($user)->test(Home::class)
        ->assertSee('Nothing booked yet')
        ->assertSee('Your next classes show up here.')
        ->assertSeeHtml('href="'.route('book').'"');
});

it('offers one Book a class button on Home once there is a booking', function () {
    $this->travelTo(Carbon::parse('2026-10-14 10:00'));
    $user = bookingCustomer();
    $session = classSession(['starts_at' => '2026-10-16 17:00']);
    app(BookSession::class)->handle($user->household, $session, [$user->household->members->first()->id], $user);

    $html = Livewire::actingAs($user)->test(Home::class)->assertDontSee('Nothing booked yet')->html();

    expect(substr_count($html, 'Book a class'))->toBe(1);
});

it('says there are no classes to book when the window is empty', function () {
    Livewire::actingAs(bookingCustomer())->test(BookPage::class)
        ->assertSee('No classes to book right now')
        ->assertSee('See my bookings');
});

it('still shows the classes when there are some to book', function () {
    $this->travelTo(Carbon::parse('2026-10-14 10:00'));
    classSession(['starts_at' => '2026-10-15 17:00', 'title' => 'Thursday Fit']);

    Livewire::actingAs(bookingCustomer())->test(BookPage::class)
        ->assertDontSee('No classes to book right now')
        ->assertSee('Thursday Fit');
});

it('shows the empty Upcoming and Past tabs of Bookings', function () {
    Livewire::actingAs(bookingCustomer())->test(BookingsPage::class)
        ->assertSee('Nothing booked yet')
        ->assertSee('Book a class')
        ->set('tab', 'past')
        ->assertSee('No past bookings');
});

it('shows the empty credit history and no package on the membership page', function () {
    Livewire::actingAs(customer())->test(MembershipPage::class)
        ->assertSee('No package yet')
        ->assertSee('No credits yet');
});

it('shows the empty timetable to a guest and a customer, each with a next step', function () {
    $this->get('/timetable')->assertSee('Nothing scheduled')->assertSee('Join OptiFit');
    $this->get('/')->assertSee('Nothing scheduled')->assertSee('Join OptiFit');

    $this->actingAs(customer())->get('/timetable')->assertSee('Nothing scheduled')->assertSee('Back to Home')->assertDontSee('Join OptiFit');
});
