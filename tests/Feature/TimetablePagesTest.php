<?php

use App\Models\GuestEntry;
use App\Models\Location;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
});

it('lists the next 14 days of scheduled classes with places left, read-only (docs/04 § /timetable)', function () {
    classSession(['starts_at' => '2026-10-19 05:00', 'title' => 'Monday Morning']);
    $full = classSession(['starts_at' => '2026-10-20 16:00', 'title' => 'Tuesday Full', 'capacity' => 1]);
    GuestEntry::factory()->for($full)->create();
    classSession(['starts_at' => '2026-10-29 05:00', 'title' => 'Last Day']);
    classSession(['starts_at' => '2026-10-30 05:00', 'title' => 'Too Far']);
    classSession(['starts_at' => '2026-10-16 05:00', 'title' => 'Earlier Today']);
    classSession(['starts_at' => '2026-10-21 05:00', 'title' => 'Cancelled Class'])->update(['status' => 'cancelled']);

    $this->get('/timetable')
        ->assertOk()
        ->assertSee('Monday Morning')
        ->assertSee('15 places left')
        ->assertSee('Tuesday Full')
        ->assertSee('Full')
        ->assertSee('Last Day')
        ->assertDontSee('Too Far')
        ->assertDontSee('Earlier Today')
        ->assertDontSee('Cancelled Class')
        ->assertSee('Log in to book');
});

it('sends signed-in customers to /book and shows no booking button to admins (docs/04)', function () {
    $this->actingAs(customer())->get('/timetable')->assertOk()->assertSee(route('book'))->assertDontSee('Log in to book');
    $this->actingAs(admin())->get('/timetable')->assertOk()->assertDontSee('Log in to book')->assertDontSee('Book a class');
});

it('shows an empty state when nothing is scheduled', function () {
    $this->get('/timetable')->assertOk()->assertSee('Nothing scheduled');
});

it('shows this week\'s classes, the next 7 days, on the landing page (docs/04 § Landing)', function () {
    classSession(['starts_at' => '2026-10-19 05:00', 'title' => 'Monday Morning']);
    classSession(['starts_at' => '2026-10-22 16:00', 'title' => 'Thursday Afternoon']);
    classSession(['starts_at' => '2026-10-23 05:00', 'title' => 'Next Friday']);

    $this->get('/')
        ->assertOk()
        ->assertSee("This week's timetable", false)
        ->assertSee('Monday Morning')
        ->assertSee('Thursday Afternoon')
        ->assertDontSee('Next Friday');
});

it('reads the footer venue from the first active location', function () {
    $this->get('/')->assertSee('Gene Louw Activity Centre')->assertSee('Tulip Street, Amanda Glen, Durbanville');

    Location::query()->update(['is_active' => false]);
    Location::factory()->create(['name' => 'Community Hall', 'address' => '1 Church Street']);

    $this->get('/pricing')->assertSee('Community Hall')->assertSee('1 Church Street')->assertDontSee('Gene Louw');
});
