<?php

use App\Actions\Memberships\ApproveMembership;
use App\Actions\Memberships\CancelMembership;
use App\Actions\Memberships\CancelMembershipNow;
use App\Actions\Memberships\DeclineMembership;
use App\Actions\Memberships\PauseMembership;
use App\Actions\Memberships\RequestMembership;
use App\Actions\Memberships\ResumeMembership;
use App\Actions\Memberships\UndoMembershipCancellation;
use App\Actions\Memberships\UpdateMembership;
use App\Actions\Memberships\WithdrawMembershipRequest;
use App\Enums\CreditEntryType;
use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Mail\AdminMembershipEnding;
use App\Mail\AdminMembershipRequest;
use App\Mail\MembershipApproved;
use App\Mail\MembershipChangeApproved;
use App\Mail\MembershipDeclined;
use App\Mail\MembershipEnding;
use App\Mail\MembershipRequested;
use App\Models\CreditEntry;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Package;
use App\Models\Settings;
use App\Models\User;
use App\Support\CreditLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * @param  list<int>|null  $memberIds  defaults to every active member
 */
function requestFor(User $user, Package $package, ?array $memberIds = null, ?string $note = null): Membership
{
    $memberIds ??= $user->household->activeMembers()->pluck('id')->all();

    return app(RequestMembership::class)->handle($user->household, $package, $memberIds, $note);
}

function approve(Membership $membership, string $startsPeriod, ?int $firstMonthCredits = null, int $credits = 8): Membership
{
    return app(ApproveMembership::class)->handle($membership, admin(), $startsPeriod, $credits, 52000, $firstMonthCredits, 'EFT ref 123');
}

beforeEach(function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
});

// --- Requesting (BR-4.1, 4.2, 4.3, 2.6) ---

it('computes the monthly credits and price from the covered members (BR-4.2)', function () {
    $user = customer(3);
    $package = Package::factory()->pricedAt([1 => 52000, 2 => 78000, 3 => 99800])->create(['credits_per_member' => 8]);
    $covered = $user->household->members->take(2)->pluck('id')->all();

    $membership = requestFor($user, $package, $covered, 'Early mornings please');

    expect($membership->status)->toBe(MembershipStatus::Requested)
        ->and($membership->monthly_credits)->toBe(16)
        ->and($membership->monthly_price_cents)->toBe(78000)
        ->and($membership->customer_note)->toBe('Early mornings please')
        ->and($membership->members->pluck('id')->sort()->values()->all())->toBe(collect($covered)->sort()->values()->all())
        ->and($membership->starts_period)->toBeNull();
});

it('queues the request emails to the customer and the admins (C3, A2)', function () {
    $user = customer();
    $package = Package::factory()->create();

    requestFor($user, $package);

    Mail::assertQueued(MembershipRequested::class, fn ($mail) => $mail->hasTo($user->email));
    Mail::assertQueued(AdminMembershipRequest::class, fn ($mail) => $mail->hasTo('info@opti-fit.co.za'));
});

it('requires an emergency contact before requesting (BR-2.6)', function () {
    $user = customer();
    $user->household->update(['emergency_contact_name' => null, 'emergency_contact_phone' => null]);

    expect(fn () => requestFor($user, Package::factory()->create()))
        ->toThrow(MembershipException::class, 'emergency contact');
    expect(Membership::query()->count())->toBe(0);
});

it('allows only one requested membership at a time (BR-4.1)', function () {
    $user = customer();
    $package = Package::factory()->create();
    requestFor($user, $package);

    expect(fn () => requestFor($user, $package))->toThrow(MembershipException::class, 'already have a package request');
    expect(Membership::query()->count())->toBe(1);
});

it('allows a change request while a membership is active (BR-4.1, 4.7)', function () {
    $user = customer();
    activeMembership($user);

    $change = requestFor($user, Package::factory()->create());

    expect($change->status)->toBe(MembershipStatus::Requested)
        ->and($user->household->memberships()->count())->toBe(2);
});

it('refuses archived members, other households\' members and inactive packages (BR-3.3, 4.2, 2.7)', function () {
    $user = customer(2);
    $archived = $user->household->members->first();
    $archived->forceFill(['archived_at' => now()])->save();
    $someoneElse = customer()->household->members->first();
    $package = Package::factory()->create();

    expect(fn () => requestFor($user, $package, [$archived->id]))->toThrow(MembershipException::class)
        ->and(fn () => requestFor($user, $package, [$someoneElse->id]))->toThrow(MembershipException::class)
        ->and(fn () => requestFor($user, $package, []))->toThrow(MembershipException::class)
        ->and(fn () => requestFor($user, Package::factory()->inactive()->create()))->toThrow(MembershipException::class, 'no longer available');
    expect(Membership::query()->count())->toBe(0);
});

it('lets a customer withdraw their own request, which deletes it (BR-4.3)', function () {
    $user = customer();
    $membership = requestFor($user, Package::factory()->create());

    app(WithdrawMembershipRequest::class)->handle($user->household, $membership);

    expect(Membership::query()->count())->toBe(0)
        ->and($membership->members()->count())->toBe(0);
});

it('does not withdraw a membership that is not a pending request, or another household\'s (BR-4.3, 2.7)', function () {
    $user = customer();
    $active = activeMembership($user);
    $theirs = requestFor($other = customer(), Package::factory()->create());

    expect(fn () => app(WithdrawMembershipRequest::class)->handle($user->household, $active))->toThrow(MembershipException::class)
        ->and(fn () => app(WithdrawMembershipRequest::class)->handle($user->household, $theirs))->toThrow(MembershipException::class);
    expect(Membership::query()->count())->toBe(2);
});

// --- Approving (BR-4.4, 4.5, 4.7) ---

it('grants the prorated first month once when approved for this month (BR-4.4, 4.5)', function () {
    $user = customer();
    $membership = requestFor($user, Package::factory()->create(['sessions_per_week' => 2, 'credits_per_member' => 8]));

    $approved = approve($membership, '2026-10');

    $entry = CreditEntry::query()->sole();
    expect($approved->status)->toBe(MembershipStatus::Active)
        ->and($approved->starts_period)->toBe('2026-10')
        ->and($approved->grants_from_period)->toBe('2026-11') // this month is settled by the approval itself
        ->and($approved->approved_at)->not->toBeNull()
        ->and($approved->admin_note)->toBe('EFT ref 123')
        ->and($entry->type)->toBe(CreditEntryType::Grant)
        ->and($entry->amount)->toBe(4) // 8 × 16/31
        ->and($entry->period)->toBe('2026-10')
        ->and($entry->idempotency_key)->toBe("grant:{$membership->id}:2026-10")
        ->and($entry->description)->toBe('October credits (2× per week, prorated)')
        ->and(CreditLedger::balance($user->household))->toBe(4);
});

it('does not grant twice when approved again (BR-4.4)', function () {
    $user = customer();
    $membership = requestFor($user, Package::factory()->create());
    approve($membership, '2026-10');

    expect(fn () => approve($membership, '2026-10'))->toThrow(MembershipException::class, 'requested');
    expect(CreditEntry::query()->count())->toBe(1);
});

it('uses the first-month credits the admin typed, and 0 writes no entry (BR-4.4)', function () {
    $custom = customer();
    approve(requestFor($custom, Package::factory()->create()), '2026-10', firstMonthCredits: 6);
    $none = customer();
    approve(requestFor($none, Package::factory()->create()), '2026-10', firstMonthCredits: 0);

    expect(CreditLedger::balance($custom->household))->toBe(6)
        ->and(CreditLedger::balance($none->household))->toBe(0)
        ->and(CreditEntry::query()->count())->toBe(1);
});

it('grants the full month, without "prorated", when approved on the 1st (BR-4.5)', function () {
    $this->travelTo(Carbon::parse('2026-10-01 08:00'));
    $user = customer();

    approve(requestFor($user, Package::factory()->create()), '2026-10');

    $entry = CreditEntry::query()->sole();
    expect($entry->amount)->toBe(8)
        ->and($entry->description)->toBe('October credits (2× per week)');
});

it('grants nothing now when approved for next month, and the monthly job grants it later (BR-4.4)', function () {
    $user = customer();

    $approved = approve(requestFor($user, Package::factory()->create()), '2026-11');

    expect($approved->starts_period)->toBe('2026-11')
        ->and($approved->grants_from_period)->toBe('2026-11')
        ->and(CreditEntry::query()->count())->toBe(0);
    Mail::assertQueued(MembershipApproved::class, fn ($mail) => $mail->hasTo($user->email) && $mail->firstMonthCredits === null);
});

it('emails the customer when approved, mentioning the first-month credits (C4)', function () {
    Settings::query()->first()->update(['payment_instructions' => 'EFT to FNB 123456']);
    $user = customer();

    approve(requestFor($user, Package::factory()->create()), '2026-10');

    Mail::assertQueued(MembershipApproved::class, fn ($mail) => $mail->firstMonthCredits === 4);
    Mail::assertNotQueued(MembershipChangeApproved::class);
});

it('only starts this month or next month (BR-4.4)', function () {
    $membership = requestFor(customer(), Package::factory()->create());

    expect(fn () => approve($membership, '2026-12'))->toThrow(MembershipException::class, 'this month or next month');
    expect($membership->fresh()->status)->toBe(MembershipStatus::Requested);
});

it('ends the old membership this period and starts the change next period (BR-4.7)', function () {
    $user = customer();
    $old = activeMembership($user, '2026-09');
    $change = requestFor($user, Package::factory()->create(['credits_per_member' => 12]));

    // Even if the admin picks "this month", a change never swaps mid-month.
    $approved = approve($change, '2026-10', credits: 12);

    expect($old->fresh()->ends_period)->toBe('2026-10')
        ->and($old->fresh()->status)->toBe(MembershipStatus::Active)
        ->and($approved->starts_period)->toBe('2026-11')
        ->and($approved->grants_from_period)->toBe('2026-11')
        ->and(CreditEntry::query()->count())->toBe(0)
        ->and($user->household->membershipInEffect('2026-10')->is($old))->toBeTrue()
        ->and($user->household->membershipInEffect('2026-11')->is($approved))->toBeTrue();
    Mail::assertQueued(MembershipChangeApproved::class, fn ($mail) => $mail->hasTo($user->email) && $mail->previous->is($old));
    Mail::assertNotQueued(MembershipApproved::class);
});

it('refuses a second approval while a package change is already scheduled (BR-4.1)', function () {
    $user = customer();
    activeMembership($user, '2026-09', ['ends_period' => '2026-10']);
    activeMembership($user, '2026-11');
    $another = Membership::factory()->for($user->household)->for(Package::factory()->create())->create();

    expect(fn () => approve($another, '2026-11'))->toThrow(MembershipException::class, 'starting next month');
});

// --- Declining (BR-4.6) ---

it('declines with a reason and emails the customer (BR-4.6)', function () {
    $user = customer();
    $membership = requestFor($user, Package::factory()->create());

    $declined = app(DeclineMembership::class)->handle($membership, 'We are fully booked this month.');

    expect($declined->status)->toBe(MembershipStatus::Declined)
        ->and($declined->decline_reason)->toBe('We are fully booked this month.');
    Mail::assertQueued(MembershipDeclined::class, fn ($mail) => $mail->hasTo($user->email));
});

it('requires a reason to decline and only declines requests (BR-4.6)', function () {
    $user = customer();
    $membership = requestFor($user, Package::factory()->create());

    expect(fn () => app(DeclineMembership::class)->handle($membership, '  '))->toThrow(MembershipException::class, 'reason')
        ->and(fn () => app(DeclineMembership::class)->handle(activeMembership(customer()), 'No'))->toThrow(MembershipException::class);
    expect($membership->fresh()->status)->toBe(MembershipStatus::Requested);
});

// --- Customer cancellation (BR-4.8) ---

it('ends the membership this period when the customer cancels, and emails everyone (BR-4.8)', function () {
    $user = customer();
    $membership = activeMembership($user, '2026-09');

    app(CancelMembership::class)->handle($user->household);

    expect($membership->fresh()->ends_period)->toBe('2026-10')
        ->and($membership->fresh()->status)->toBe(MembershipStatus::Active)
        ->and($membership->fresh()->isEnding())->toBeTrue();
    Mail::assertQueued(MembershipEnding::class, fn ($mail) => $mail->hasTo($user->email));
    Mail::assertQueued(AdminMembershipEnding::class, fn ($mail) => $mail->hasTo('info@opti-fit.co.za'));
});

it('lets the customer undo the cancellation before month end (BR-4.8)', function () {
    $user = customer();
    $membership = activeMembership($user, '2026-09');
    app(CancelMembership::class)->handle($user->household);

    app(UndoMembershipCancellation::class)->handle($user->household);

    expect($membership->fresh()->ends_period)->toBeNull()
        ->and($membership->fresh()->isEnding())->toBeFalse();
});

it('has nothing to cancel or undo without an active membership (BR-4.8)', function () {
    $user = customer();

    expect(fn () => app(CancelMembership::class)->handle($user->household))->toThrow(MembershipException::class)
        ->and(fn () => app(UndoMembershipCancellation::class)->handle($user->household))->toThrow(MembershipException::class);

    $paused = customer();
    Membership::factory()->for($paused->household)->for(Package::factory()->create())->paused()->create();
    expect(fn () => app(CancelMembership::class)->handle($paused->household))->toThrow(MembershipException::class);
});

it('cannot be cancelled twice (BR-4.8)', function () {
    $user = customer();
    activeMembership($user, '2026-09');
    app(CancelMembership::class)->handle($user->household);

    expect(fn () => app(CancelMembership::class)->handle($user->household))->toThrow(MembershipException::class, 'already ending');
});

it('blocks cancelling or undoing while a package change is scheduled (BR-4.7, 4.8)', function () {
    $user = customer();
    $old = activeMembership($user, '2026-09', ['ends_period' => '2026-10']);
    activeMembership($user, '2026-11');

    expect(fn () => app(CancelMembership::class)->handle($user->household))->toThrow(MembershipException::class, 'changing next month')
        ->and(fn () => app(UndoMembershipCancellation::class)->handle($user->household))->toThrow(MembershipException::class, 'changing next month');
    expect($old->fresh()->ends_period)->toBe('2026-10');
});

// --- Admin pause, resume, cancel now, edit (BR-4.9) ---

it('pauses a membership so the job stops granting for it (BR-4.9)', function () {
    $membership = activeMembership(customer(), '2026-09');

    $paused = app(PauseMembership::class)->handle($membership);

    expect($paused->status)->toBe(MembershipStatus::Paused);
    expect(fn () => app(PauseMembership::class)->handle($membership))->toThrow(MembershipException::class);
});

it('resumes at once when this month was already granted (BR-4.9)', function () {
    $user = customer();
    $membership = activeMembership($user, '2026-09');
    CreditLedger::write($user->household, '2026-10', CreditEntryType::Grant, 8, 'October credits', $membership, key: "grant:{$membership->id}:2026-10");
    app(PauseMembership::class)->handle($membership);

    $resumed = app(ResumeMembership::class)->handle($membership);

    expect($resumed->status)->toBe(MembershipStatus::Active)
        ->and($resumed->starts_period)->toBe('2026-09')
        ->and($resumed->grants_from_period)->toBe('2026-11');
});

it('resumes without rewriting the start and grants from the next 1st (BR-4.9)', function () {
    $user = customer();
    $membership = activeMembership($user, '2026-09');
    app(PauseMembership::class)->handle($membership);

    $resumed = app(ResumeMembership::class)->handle($membership);

    expect($resumed->status)->toBe(MembershipStatus::Active)
        ->and($resumed->starts_period)->toBe('2026-09')
        ->and($resumed->grants_from_period)->toBe('2026-11')
        ->and($user->household->membershipInEffect()?->is($membership))->toBeTrue()
        ->and($user->household->scheduledMembership())->toBeNull();
    expect(fn () => app(ResumeMembership::class)->handle($membership))->toThrow(MembershipException::class);
});

it('cancels now, ending this period and leaving the ledger alone (BR-4.9)', function () {
    $user = customer();
    $membership = activeMembership($user, '2026-09');
    CreditLedger::write($user->household, '2026-10', CreditEntryType::Grant, 8, 'October credits', $membership, key: "grant:{$membership->id}:2026-10");

    $cancelled = app(CancelMembershipNow::class)->handle($membership);

    expect($cancelled->status)->toBe(MembershipStatus::Cancelled)
        ->and($cancelled->ends_period)->toBe('2026-10')
        ->and(CreditLedger::balance($user->household))->toBe(8)
        ->and(CreditEntry::query()->count())->toBe(1);
    expect(fn () => app(CancelMembershipNow::class)->handle($membership))->toThrow(MembershipException::class);
});

it('edits credits, price, members and note without touching the ledger (BR-4.9)', function () {
    $user = customer(3);
    $membership = activeMembership($user, '2026-09');
    $newMembers = $user->household->members->take(2)->pluck('id')->all();

    app(UpdateMembership::class)->handle($membership, 20, 90000, $newMembers, 'Paid in advance');

    $membership->refresh();
    expect($membership->monthly_credits)->toBe(20)
        ->and($membership->monthly_price_cents)->toBe(90000)
        ->and($membership->admin_note)->toBe('Paid in advance')
        ->and($membership->members()->count())->toBe(2)
        ->and(CreditEntry::query()->count())->toBe(0);
});

it('only lets an edit cover active members of the same household (BR-4.9, 2.5)', function () {
    $user = customer(2);
    $membership = activeMembership($user, '2026-09');
    $user->household->members->first()->forceFill(['archived_at' => now()])->save();
    $archived = $user->household->members()->whereNotNull('archived_at')->first();
    $stranger = Member::factory()->create();

    expect(fn () => app(UpdateMembership::class)->handle($membership, 8, 52000, [$archived->id], null))->toThrow(MembershipException::class)
        ->and(fn () => app(UpdateMembership::class)->handle($membership, 8, 52000, [$stranger->id], null))->toThrow(MembershipException::class)
        ->and(fn () => app(UpdateMembership::class)->handle($membership, 8, 52000, [], null))->toThrow(MembershipException::class);
});

it('only edits active or paused memberships (BR-4.9)', function () {
    $requested = requestFor(customer(), Package::factory()->create());

    expect(fn () => app(UpdateMembership::class)->handle($requested, 8, 52000, $requested->members->pluck('id')->all(), null))
        ->toThrow(MembershipException::class);
});

it('refuses a new request while a package change is already scheduled (BR-4.1, 4.7)', function () {
    $user = customer();
    activeMembership($user, '2026-09', ['ends_period' => '2026-10']);
    activeMembership($user, '2026-11');

    expect(fn () => requestFor($user, Package::factory()->create()))
        ->toThrow(MembershipException::class, 'already changes on 1 November');
    expect(Membership::query()->where('status', MembershipStatus::Requested)->count())->toBe(0);
});
