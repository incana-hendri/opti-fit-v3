<?php

use App\Enums\CreditEntryType;
use App\Livewire\BookPage;
use App\Mail\BookingConfirmed;
use App\Models\Booking;
use App\Models\CreditEntry;
use App\Models\GuestEntry;
use App\Models\Member;
use App\Support\CreditLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

// Friday 16 Oct 2026, 10:00. Window: until Thu 29 Oct. The Monday 19 Oct 05:00 class closes on Sunday at 21:00.
beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $this->user = bookingCustomer();
    $this->household = $this->user->household;
    $this->session = classSession(['starts_at' => '2026-10-19 05:00', 'title' => 'Morning Functional Fitness']);
});

it('lists the classes of the booking window grouped by day with a day strip (docs/04)', function () {
    $later = classSession(['starts_at' => '2026-10-21 16:00', 'title' => 'Afternoon']);
    classSession(['starts_at' => '2026-10-30 05:00', 'title' => 'Beyond the horizon']);
    classSession(['starts_at' => '2026-10-15 05:00', 'title' => 'Yesterday']);
    classSession(['starts_at' => '2026-10-20 05:00', 'title' => 'Cancelled one'])->update(['status' => 'cancelled']);

    $this->actingAs($this->user)->get('/book')
        ->assertOk()
        ->assertSee('Morning Functional Fitness')
        ->assertSee('Afternoon')
        ->assertDontSee('Beyond the horizon')
        ->assertDontSee('Yesterday')
        ->assertDontSee('Cancelled one')
        ->assertSee('id="day-2026-10-19"', false)
        ->assertSee('id="day-2026-10-29"', false)
        ->assertDontSee('id="day-2026-10-30"', false)
        ->assertSee('8 credits left this month');
});

it('shows the places left, who is booked and why a class is closed, full or all booked (docs/04)', function () {
    $closed = classSession(['starts_at' => '2026-10-16 16:00', 'title' => 'Closed class']);
    $full = classSession(['starts_at' => '2026-10-20 05:00', 'title' => 'Full class', 'capacity' => 1]);
    GuestEntry::factory()->for($full)->create();
    $booked = classSession(['starts_at' => '2026-10-21 05:00', 'title' => 'Booked class']);
    foreach ($this->household->members as $member) {
        Booking::factory()->for($booked, 'classSession')->create(['member_id' => $member->id]);
    }

    $page = Livewire::actingAs($this->user)->test(BookPage::class);

    $page->assertSee('15 places left')
        ->assertSee('Booking closed.')
        ->assertSee('Bookings close 8 hours before the class.')
        ->assertSee('Full')
        ->assertSee('All booked')
        ->assertViewHas('states', fn ($states) => $states[$closed->id] === 'closed'
            && $states[$full->id] === 'full'
            && $states[$booked->id] === 'all-booked'
            && $states[$this->session->id] === 'bookable');
    foreach ($this->household->members as $member) {
        $page->assertSee($member->first_name);
    }
});

it('books the chosen members, shows the toast and updates the card (docs/04, BR-7.3)', function () {
    $members = $this->household->members;

    Livewire::actingAs($this->user)->test(BookPage::class)
        ->call('openBook', $this->session->id)
        ->assertSet('memberIds', $members->pluck('id')->all())
        ->assertSee('2 credits')
        ->assertSee("you'll have 6 left", false)
        ->assertSee('Free cancellation until Sun 18 Oct 21:00')
        ->assertSee('Book 2 people')
        ->set('memberIds', [(string) $members[0]->id])
        ->assertSee('1 credit')
        ->assertSee('Book 1 person')
        ->call('book')
        ->assertDispatched('toast', message: 'Booked! See you Monday 05:00')
        ->assertDispatched('close-sheet', name: 'book')
        ->assertSet('sessionId', null)
        ->assertSee($members[0]->first_name);

    expect(Booking::query()->where('member_id', $members[0]->id)->count())->toBe(1)
        ->and(CreditLedger::balance($this->household))->toBe(7);
    Mail::assertQueued(BookingConfirmed::class, 1);
});

it('ticks and disables members who are already booked and does not offer them again (docs/04)', function () {
    Booking::factory()->for($this->session, 'classSession')->create(['member_id' => $this->household->members[0]->id]);

    Livewire::actingAs($this->user)->test(BookPage::class)
        ->call('openBook', $this->session->id)
        ->assertSet('memberIds', [$this->household->members[1]->id])
        ->assertSee('Already booked')
        ->assertSee('Book 1 person');
});

it('does not list archived or uncovered members in the sheet (docs/04)', function () {
    $archived = $this->household->members[0];
    $archived->forceFill(['archived_at' => now()])->save();
    $uncovered = Member::factory()->for($this->household)->create(['first_name' => 'Zelda']);

    Livewire::actingAs($this->user)->test(BookPage::class)
        ->call('openBook', $this->session->id)
        ->assertDontSee($archived->first_name)
        ->assertDontSee('Zelda')
        ->assertSee($this->household->members[1]->first_name);
});

it('shows an error inline when the place has just gone (docs/04)', function () {
    $page = Livewire::actingAs($this->user)->test(BookPage::class)->call('openBook', $this->session->id);

    $this->session->update(['capacity' => 1]);
    GuestEntry::factory()->for($this->session)->create();

    $page->call('book')
        ->assertSet('error', 'This class is full.')
        ->assertSee('This class is full.')
        ->assertNotDispatched('toast');
    expect(Booking::query()->count())->toBe(0);
});

it('disables the button when the credits or places are not enough', function () {
    CreditEntry::factory()->for($this->household)->ofType(CreditEntryType::Adjustment, -7)->create();

    Livewire::actingAs($this->user)->test(BookPage::class)
        ->call('openBook', $this->session->id)
        ->assertSee('You only have 1 credit.')
        ->assertSeeHtml('disabled');
});

it('explains the problem and links to the fix without a package or without credits (docs/04)', function () {
    $noPackage = customer();
    Livewire::actingAs($noPackage)->test(BookPage::class)
        ->assertSee('You need a package to book')
        ->assertSee('Get a package')
        ->assertSee(route('membership.request'));

    $empty = customer();
    activeMembership($empty);
    Livewire::actingAs($empty)->test(BookPage::class)
        ->assertSee('No credits')
        ->assertSee("You've used all your credits this month")
        ->assertSee(route('membership'));
});

it('does not book another household\'s member from the page (BR-2.7)', function () {
    $stranger = bookingCustomer();

    Livewire::actingAs($this->user)->test(BookPage::class)
        ->call('openBook', $this->session->id)
        ->set('memberIds', [(string) $stranger->household->members->first()->id])
        ->call('book')
        ->assertNotDispatched('toast')
        ->assertSee("That person isn't covered by your package.");

    expect(Booking::query()->count())->toBe(0)
        ->and(CreditLedger::balance($stranger->household))->toBe(8);
});

it('does not let the browser change which class is being booked', function () {
    $page = Livewire::actingAs($this->user)->test(BookPage::class)->call('openBook', $this->session->id);

    expect(fn () => $page->set('sessionId', 999))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('refuses to open a class that does not exist or was cancelled', function () {
    $cancelled = classSession(['starts_at' => '2026-10-20 05:00'])->fresh();
    $cancelled->update(['status' => 'cancelled']);

    Livewire::actingAs($this->user)->test(BookPage::class)->call('openBook', $cancelled->id)->assertNotFound();
});

it('avoids a query per card (docs/04 performance)', function () {
    foreach (range(1, 10) as $i) {
        classSession(['starts_at' => Carbon::parse('2026-10-19 05:00')->addHours($i)]);
    }

    DB::enableQueryLog();
    $this->actingAs($this->user)->get('/book')->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThan(15);
});
