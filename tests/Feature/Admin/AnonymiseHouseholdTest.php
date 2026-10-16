<?php

use App\Actions\Households\AnonymiseHousehold;
use App\Enums\BookingStatus;
use App\Enums\CreditEntryType;
use App\Enums\MembershipStatus;
use App\Exceptions\AccountException;
use App\Filament\Resources\Households\HouseholdResource;
use App\Filament\Resources\Households\Pages\ListHouseholds;
use App\Filament\Resources\Households\Pages\ViewHousehold;
use App\Filament\Widgets\NeedsAttention;
use App\Models\Booking;
use App\Models\CreditEntry;
use App\Models\Household;
use App\Models\Membership;
use App\Models\User;
use App\Support\CreditLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

// BR-9.6: anonymise household (POPIA erasure)
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $this->admin = admin();
});

/**
 * A household with everything the action must handle: two members with personal data, an active membership
 * and a pending change request, a ledger, a past and a future booking, a session and a reset token.
 *
 * @return array{User, Household}
 */
function erasableHousehold(): array
{
    $user = customer(2);
    $user->forceFill([
        'first_name' => 'Anna', 'last_name' => 'Smith', 'email' => 'anna@example.com', 'phone' => '082 123 4567',
        'date_of_birth' => '1985-06-15', 'marketing_opt_in' => true,
    ])->save();
    $household = $user->household;
    $household->update(['name' => 'Smith family', 'emergency_contact_name' => 'Grandma Smith', 'emergency_contact_phone' => '083 765 4321']);
    foreach ($household->members as $index => $member) {
        $member->forceFill([
            'first_name' => ['Anna', 'Mia'][$index], 'last_name' => 'Smith', 'date_of_birth' => ['1985-06-15', '2015-03-09'][$index],
            'health_notes' => 'Bad knee', 'is_account_holder' => $index === 0,
        ])->save();
    }

    $membership = activeMembership($user);
    $membership->update(['customer_note' => 'My son is called Liam', 'admin_note' => 'EFT ref SMITH1']);
    Membership::factory()->for($household)->create(['customer_note' => 'Please add Liam']);
    CreditEntry::factory()->for($household)->create(['amount' => 8]);

    $past = classSession(['starts_at' => '2026-10-14 05:00']);
    $future = classSession(['starts_at' => '2026-10-17 05:00']);
    $started = classSession(['starts_at' => '2026-10-16 09:00']);
    Booking::factory()->for($household)->for($household->members[0], 'member')->for($past, 'classSession')->attended()->create();
    Booking::factory()->for($household)->for($household->members[0], 'member')->for($started, 'classSession')->create();
    foreach ($household->members as $member) {
        $booking = Booking::factory()->for($household)->for($member, 'member')->for($future, 'classSession')->create();
        CreditEntry::factory()->for($household)->ofType(CreditEntryType::Booking, -1)->create(['booking_id' => $booking->id]);
    }

    DB::table('password_reset_tokens')->insert(['email' => 'anna@example.com', 'token' => 'abc', 'created_at' => now()]);

    return [$user, $household];
}

it('scrubs the personal data (BR-9.6)', function () {
    [$user, $household] = erasableHousehold();
    $memberIds = $household->members->pluck('id')->all();

    app(AnonymiseHousehold::class)->handle($household, $this->admin);

    $user->refresh();
    $household->refresh();

    expect($user->first_name)->toBe('Deleted')
        ->and($user->last_name)->toBe("User {$user->id}")
        ->and($user->email)->toBe("deleted-{$user->id}@invalid.local")
        ->and($user->phone)->toBeNull()
        ->and($user->date_of_birth->toDateString())->toBe('1985-01-01')
        ->and($user->marketing_opt_in)->toBeFalse()
        ->and($user->isDeactivated())->toBeTrue()
        ->and($user->remember_token)->toBeNull()
        ->and($household->name)->toBe("Household {$household->id}")
        ->and($household->emergency_contact_name)->toBeNull()
        ->and($household->emergency_contact_phone)->toBeNull()
        ->and($household->anonymised_at)->not->toBeNull()
        ->and($household->members->pluck('id')->all())->toBe($memberIds);

    foreach ($household->members()->get() as $member) {
        expect($member->fullName())->toBe("Member {$member->id}")
            ->and($member->health_notes)->toBeNull()
            ->and($member->isArchived())->toBeTrue()
            ->and($member->date_of_birth->format('m-d'))->toBe('01-01');
    }
    expect($household->members()->orderBy('id')->get()->map(fn ($member) => $member->date_of_birth->year)->all())->toBe([1985, 2015]);
    expect(DB::table('members')->whereIn('id', $memberIds)->whereNotNull('health_notes')->exists())->toBeFalse();
});

it('keeps the ledger, the past bookings and the counts (BR-9.6)', function () {
    [, $household] = erasableHousehold();
    $entries = CreditEntry::query()->where('household_id', $household->id)->count();
    $balance = CreditLedger::balance($household);
    $bookings = Booking::query()->where('household_id', $household->id)->count();

    app(AnonymiseHousehold::class)->handle($household, $this->admin);

    expect(CreditEntry::query()->where('household_id', $household->id)->count())->toBe($entries)
        ->and(CreditLedger::balance($household))->toBe($balance)
        ->and(Booking::query()->where('household_id', $household->id)->count())->toBe($bookings)
        ->and(Booking::query()->where('household_id', $household->id)->where('status', BookingStatus::Attended)->count())->toBe(1)
        // A class that has already started is not cancelled.
        ->and(Booking::query()->where('household_id', $household->id)->where('status', BookingStatus::Booked)->count())->toBe(1);
});

it('cancels the future bookings without refunds and without emails (BR-9.6)', function () {
    Mail::fake();
    [, $household] = erasableHousehold();
    $refunds = CreditEntry::query()->where('type', CreditEntryType::Refund)->count();

    app(AnonymiseHousehold::class)->handle($household, $this->admin);

    $cancelled = Booking::query()->where('household_id', $household->id)->where('status', BookingStatus::Cancelled)->get();

    expect($cancelled)->toHaveCount(2)
        ->and($cancelled->every(fn (Booking $booking) => ! $booking->late_cancellation && $booking->cancelled_by === $this->admin->id && $booking->cancelled_at !== null))->toBeTrue()
        ->and(CreditEntry::query()->where('type', CreditEntryType::Refund)->count())->toBe($refunds);
    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

it('ends the active membership, withdraws the pending request and blanks the customer notes (BR-9.6)', function () {
    [, $household] = erasableHousehold();

    app(AnonymiseHousehold::class)->handle($household, $this->admin);

    $memberships = $household->memberships()->get();

    expect($memberships)->toHaveCount(1)
        ->and($memberships[0]->status)->toBe(MembershipStatus::Cancelled)
        ->and($memberships[0]->ends_period)->toBe('2026-10')
        ->and($memberships[0]->customer_note)->toBeNull()
        ->and($memberships[0]->admin_note)->toBe('EFT ref SMITH1');
});

it('ends the login: the old email and the placeholder both fail, and sessions and reset links are gone (BR-9.6, 1.8)', function () {
    config(['session.driver' => 'database']);
    [$user, $household] = erasableHousehold();
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
    $placeholder = "deleted-{$user->id}@invalid.local";

    app(AnonymiseHousehold::class)->handle($household, $this->admin);

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('email', 'anna@example.com')->exists())->toBeFalse();

    $this->post('/login', ['email' => 'anna@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->post('/login', ['email' => $placeholder, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
    expect($user->fresh()->isDeactivated())->toBeTrue();
});

it('refuses a household that is already anonymised', function () {
    [, $household] = erasableHousehold();
    app(AnonymiseHousehold::class)->handle($household, $this->admin);

    expect(fn () => app(AnonymiseHousehold::class)->handle($household->fresh(), $this->admin))
        ->toThrow(AccountException::class, 'This household has already been anonymised.');
});

it('needs the typed confirmation on the household page (BR-9.6)', function () {
    $this->actingAs($this->admin);
    [, $household] = erasableHousehold();
    $page = fn () => Livewire::test(ViewHousehold::class, ['record' => $household->getRouteKey()]);

    $page()->callAction('anonymise', ['confirmation' => ''])->assertHasActionErrors(['confirmation']);
    $page()->callAction('anonymise', ['confirmation' => 'anonymise'])->assertHasActionErrors(['confirmation']);
    $page()->callAction('anonymise', ['confirmation' => 'yes please'])->assertHasActionErrors(['confirmation']);
    expect($household->fresh()->isAnonymised())->toBeFalse();

    $page()->callAction('anonymise', ['confirmation' => 'ANONYMISE'])->assertHasNoActionErrors()->assertNotified('Household anonymised')->assertRedirect(HouseholdResource::getUrl('view', ['record' => $household]));
    expect($household->fresh()->isAnonymised())->toBeTrue();
});

it('shows an anonymised household as such and hides the actions that no longer make sense', function () {
    $this->actingAs($this->admin);
    [, $household] = erasableHousehold();
    app(AnonymiseHousehold::class)->handle($household, $this->admin);

    $this->get("/admin/households/{$household->id}")->assertOk()->assertSee('Anonymised')->assertSee('Household '.$household->id);

    Livewire::test(ViewHousehold::class, ['record' => $household->getRouteKey()])
        ->assertActionHidden('anonymise')
        ->assertActionHidden('reactivate')
        ->assertActionHidden('deactivate')
        ->assertActionHidden('sendPasswordReset');
});

it('stops counting a deletion request once the household is anonymised (BR-1.6)', function () {
    $this->actingAs($this->admin);
    [$user, $household] = erasableHousehold();
    $user->forceFill(['deletion_requested_at' => now()])->save();
    $count = fn () => preg_replace('/\s+/', ' ', strip_tags(Livewire::test(NeedsAttention::class)->html()));

    expect($count())->toContain('Deletion requests 1');

    app(AnonymiseHousehold::class)->handle($household, $this->admin);

    expect($count())->toContain('Deletion requests 0');
    Livewire::withQueryParams(['filters' => ['deletion_requested' => ['isActive' => true]]])
        ->test(ListHouseholds::class)
        ->assertCanNotSeeTableRecords([$household->fresh()]);
});

it('locks the household first and then the sessions of the bookings it cancels (BR-7.6)', function () {
    [, $household] = erasableHousehold();
    $locks = [];
    DB::listen(function ($query) use (&$locks) {
        if (str_contains($query->sql, 'for update')) {
            $locks[] = preg_match('/from `(\w+)`/', $query->sql, $table) ? $table[1] : $query->sql;
        }
    });

    app(AnonymiseHousehold::class)->handle($household, $this->admin);

    $households = array_search('households', $locks, true);
    $sessions = array_search('class_sessions', $locks, true);

    expect($households)->toBe(0)->and($sessions)->toBeInt()->toBeGreaterThan($households);
});
