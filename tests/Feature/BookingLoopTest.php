<?php

use App\Actions\Credits\ReturnCredit;
use App\Livewire\BookingsPage;
use App\Livewire\BookPage;
use App\Models\Booking;
use App\Support\CreditLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

// docs/10 M3 acceptance: browse, book 2 members, see credits drop, cancel one before the cutoff (credit back),
// late-cancel the other (no credit), and the admin returns it manually.
it('runs the whole booking loop (docs/10 M3)', function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $user = bookingCustomer(2, 8);
    $session = classSession(['starts_at' => '2026-10-19 05:00']);
    [$mia, $liam] = $user->household->members;

    $this->actingAs($user)->get('/book')->assertOk()->assertSee('Morning Functional Fitness')->assertSee('8 credits left');

    Livewire::actingAs($user)->test(BookPage::class)
        ->call('openBook', $session->id)
        ->call('book')
        ->assertDispatched('toast', message: 'Booked! See you Monday 05:00');
    expect(CreditLedger::balance($user->household))->toBe(6);

    $this->actingAs($user)->get('/home')->assertSee('6')->assertSee('2 of 8 used this month');

    Livewire::actingAs($user)->test(BookingsPage::class)
        ->call('confirmCancel', Booking::query()->where('member_id', $mia->id)->value('id'))
        ->assertSee('Your credit will be returned.')
        ->call('cancel');
    expect(CreditLedger::balance($user->household))->toBe(7);

    $this->travelTo(Carbon::parse('2026-10-18 21:30'));
    $liamsBooking = Booking::query()->where('member_id', $liam->id)->sole();
    Livewire::actingAs($user)->test(BookingsPage::class)
        ->call('confirmCancel', $liamsBooking->id)
        ->assertSee('Late cancellation.')
        ->call('cancel');
    expect(CreditLedger::balance($user->household))->toBe(7)
        ->and($liamsBooking->fresh()->late_cancellation)->toBeTrue();

    app(ReturnCredit::class)->handle($liamsBooking->fresh(), admin());
    expect(CreditLedger::balance($user->household))->toBe(8);
});
