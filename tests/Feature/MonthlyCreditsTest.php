<?php

use App\Actions\Credits\AdjustCredits;
use App\Actions\Credits\ExpireCredits;
use App\Actions\Memberships\ApproveMembership;
use App\Actions\Memberships\PauseMembership;
use App\Actions\Memberships\ResumeMembership;
use App\Enums\CreditEntryType;
use App\Enums\MembershipStatus;
use App\Exceptions\CreditException;
use App\Mail\MonthlyCreditsReady;
use App\Models\CreditEntry;
use App\Models\Membership;
use App\Models\Package;
use App\Support\CreditLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

/**
 * September ended with 5 unused credits for a membership that has run since September.
 */
function householdWithSeptemberLeftovers(): array
{
    test()->travelTo(Carbon::parse('2026-09-10 12:00'));
    $user = customer();
    $membership = activeMembership($user, '2026-09');
    CreditLedger::write($user->household, '2026-09', CreditEntryType::Grant, 8, 'September credits', $membership, key: "grant:{$membership->id}:2026-09");
    CreditLedger::write($user->household, '2026-09', CreditEntryType::Adjustment, -3, 'Adjustment: test');

    return [$user, $membership];
}

it('expires last month and grants this month on the 1st, sending one email (BR-5.5)', function () {
    [$user, $membership] = householdWithSeptemberLeftovers();
    $this->travelTo(Carbon::parse('2026-10-01 00:05'));

    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    $expiry = CreditEntry::query()->where('type', CreditEntryType::Expiry)->sole();
    $grant = CreditEntry::query()->where('period', '2026-10')->sole();
    expect($expiry->amount)->toBe(-5)
        ->and($expiry->period)->toBe('2026-09')
        ->and($expiry->idempotency_key)->toBe("expiry:{$user->household->id}:2026-09")
        ->and($expiry->description)->toBe('Unused September credits expired')
        ->and($grant->amount)->toBe(8)
        ->and($grant->description)->toBe('October credits (2× per week)')
        ->and(CreditLedger::balance($user->household, '2026-09'))->toBe(0)
        ->and(CreditLedger::balance($user->household))->toBe(8);
    Mail::assertQueued(MonthlyCreditsReady::class, 1);
    Mail::assertQueued(MonthlyCreditsReady::class, fn ($mail) => $mail->hasTo($user->email) && $mail->period === '2026-10');
});

it('changes nothing when it runs twice (BR-5.5)', function () {
    householdWithSeptemberLeftovers();
    $this->travelTo(Carbon::parse('2026-10-01 00:05'));
    $this->artisan('optifit:monthly-credits')->assertSuccessful();
    $entries = CreditEntry::query()->count();

    $this->artisan('optifit:monthly-credits')->assertSuccessful();
    $this->travelTo(Carbon::parse('2026-10-02 00:05'));
    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    expect(CreditEntry::query()->count())->toBe($entries)->toBe(4); // 2 September + expiry + grant
    Mail::assertQueued(MonthlyCreditsReady::class, 1);
});

it('gives the right result when the 1st was missed and it first runs on the 3rd (BR-5.5)', function () {
    [$user] = householdWithSeptemberLeftovers();
    $this->travelTo(Carbon::parse('2026-10-03 00:05'));

    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    expect(CreditLedger::balance($user->household, '2026-09'))->toBe(0)
        ->and(CreditLedger::balance($user->household))->toBe(8);
    Mail::assertQueued(MonthlyCreditsReady::class, 1);
});

it('expires every past period that still has credits, not only last month (BR-5.5)', function () {
    $this->travelTo(Carbon::parse('2026-08-10'));
    $user = customer();
    CreditLedger::write($user->household, '2026-08', CreditEntryType::Adjustment, 2, 'Adjustment: gift');
    CreditLedger::write($user->household, '2026-09', CreditEntryType::Adjustment, 4, 'Adjustment: gift');
    $this->travelTo(Carbon::parse('2026-10-05'));

    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    expect(CreditLedger::balance($user->household, '2026-08'))->toBe(0)
        ->and(CreditLedger::balance($user->household, '2026-09'))->toBe(0)
        ->and(CreditEntry::query()->where('type', CreditEntryType::Expiry)->count())->toBe(2);
});

it('writes no expiry for balances that are zero (BR-5.5)', function () {
    $this->travelTo(Carbon::parse('2026-09-10'));
    $user = customer();
    CreditLedger::write($user->household, '2026-09', CreditEntryType::Adjustment, 3, 'Adjustment: gift');
    CreditLedger::write($user->household, '2026-09', CreditEntryType::Adjustment, -3, 'Adjustment: correction');
    $this->travelTo(Carbon::parse('2026-10-01'));

    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    expect(CreditEntry::query()->where('type', CreditEntryType::Expiry)->count())->toBe(0);
});

it('cancels memberships once their last period has passed and grants them nothing (BR-4.8, 5.5)', function () {
    $this->travelTo(Carbon::parse('2026-09-10'));
    $user = customer();
    $membership = activeMembership($user, '2026-08', ['ends_period' => '2026-09']);
    $this->travelTo(Carbon::parse('2026-10-01 00:05'));

    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Cancelled)
        ->and(CreditEntry::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('still grants in the last period of a membership that is ending (BR-5.5 step 3)', function () {
    $this->travelTo(Carbon::parse('2026-10-01'));
    $user = customer();
    $membership = activeMembership($user, '2026-08', ['ends_period' => '2026-10']);

    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    expect($membership->fresh()->status)->toBe(MembershipStatus::Active)
        ->and(CreditLedger::balance($user->household))->toBe(8);
});

it('grants only active memberships that have started (BR-5.5 step 3)', function () {
    $this->travelTo(Carbon::parse('2026-10-01'));
    $paused = customer();
    Membership::factory()->for($paused->household)->paused()->create();
    $scheduled = customer();
    activeMembership($scheduled, '2026-11');
    $requested = customer();
    Membership::factory()->for($requested->household)->create();
    $declined = customer();
    Membership::factory()->for($declined->household)->declined()->create();

    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    expect(CreditEntry::query()->count())->toBe(0);
});

it('skips a paused month and restores grants from the next 1st after a resume (BR-4.9)', function () {
    $this->travelTo(Carbon::parse('2026-10-01 00:05'));
    $user = customer();
    $membership = activeMembership($user, '2026-09');
    $this->artisan('optifit:monthly-credits')->assertSuccessful();
    expect(CreditLedger::balance($user->household))->toBe(8);

    $this->travelTo(Carbon::parse('2026-10-10'));
    app(PauseMembership::class)->handle($membership);
    $this->travelTo(Carbon::parse('2026-11-01 00:05'));
    $this->artisan('optifit:monthly-credits')->assertSuccessful();
    expect(CreditLedger::balance($user->household, '2026-11'))->toBe(0);

    $this->travelTo(Carbon::parse('2026-11-05'));
    app(ResumeMembership::class)->handle($membership);
    expect($membership->fresh()->starts_period)->toBe('2026-09')
        ->and($user->household->membershipInEffect()?->is($membership))->toBeTrue();
    $this->travelTo(Carbon::parse('2026-11-06 00:05'));
    $this->artisan('optifit:monthly-credits')->assertSuccessful();
    expect(CreditLedger::balance($user->household, '2026-11'))->toBe(0); // no mid-month grant

    $this->travelTo(Carbon::parse('2026-12-01 00:05'));
    $this->artisan('optifit:monthly-credits')->assertSuccessful();
    expect(CreditLedger::balance($user->household, '2026-12'))->toBe(8);
});

it('grants a changed membership its new credits from the next grant (BR-4.9)', function () {
    $this->travelTo(Carbon::parse('2026-10-01'));
    $user = customer();
    activeMembership($user, '2026-09', ['monthly_credits' => 12]);

    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    expect(CreditLedger::balance($user->household))->toBe(12);
});

it('is scheduled daily at 00:05 (docs/07)', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('optifit:monthly-credits')
        ->assertSuccessful();
});

// --- Expire and adjust actions ---

it('refuses to expire the current period (BR-5.5)', function () {
    $user = customer();

    expect(fn () => app(ExpireCredits::class)->handle($user->household, now()->format('Y-m')))
        ->toThrow(CreditException::class);
});

it('adds credits to the current period with a reason (BR-6.5)', function () {
    $this->travelTo(Carbon::parse('2026-10-14'));
    $user = customer();
    $admin = admin();

    $entry = app(AdjustCredits::class)->handle($user->household, 3, 'Make-up for a cancelled class', $admin);

    expect($entry->type)->toBe(CreditEntryType::Adjustment)
        ->and($entry->amount)->toBe(3)
        ->and($entry->period)->toBe('2026-10')
        ->and($entry->description)->toBe('Adjustment: Make-up for a cancelled class')
        ->and($entry->created_by)->toBe($admin->id)
        ->and(CreditLedger::balance($user->household))->toBe(3);
});

it('removes credits but never below zero (BR-5.4, 6.5)', function () {
    $user = customer();
    $admin = admin();
    app(AdjustCredits::class)->handle($user->household, 2, 'Gift', $admin);

    app(AdjustCredits::class)->handle($user->household, -2, 'Correction', $admin);
    expect(CreditLedger::balance($user->household))->toBe(0);

    app(AdjustCredits::class)->handle($user->household, 2, 'Gift', $admin);
    expect(fn () => app(AdjustCredits::class)->handle($user->household, -3, 'Too many', $admin))
        ->toThrow(CreditException::class, 'below zero');
    expect(CreditLedger::balance($user->household))->toBe(2)
        ->and(CreditEntry::query()->count())->toBe(3);
});

it('needs a non-zero amount and a reason (BR-6.5)', function () {
    $user = customer();

    expect(fn () => app(AdjustCredits::class)->handle($user->household, 0, 'Nothing', admin()))->toThrow(CreditException::class)
        ->and(fn () => app(AdjustCredits::class)->handle($user->household, 2, '   ', admin()))->toThrow(CreditException::class, 'reason');
    expect(CreditEntry::query()->count())->toBe(0);
});

// --- A grant is due only from grants_from_period (BR-4.4, 4.9, 5.5) ---

it('does not grant the next day when approval gave 0 first-month credits (BR-4.4)', function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $user = customer();
    $request = Membership::factory()->for($user->household)->for(Package::factory()->create())->create();
    app(ApproveMembership::class)->handle($request, admin(), '2026-10', 8, 52000, 0);

    $this->travelTo(Carbon::parse('2026-10-17 00:05'));
    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    expect(CreditEntry::query()->count())->toBe(0);
    Mail::assertNotQueued(MonthlyCreditsReady::class);

    $this->travelTo(Carbon::parse('2026-11-01 00:05'));
    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    expect(CreditLedger::balance($user->household))->toBe(8);
    Mail::assertQueued(MonthlyCreditsReady::class, 1);
});

it('does not grant again the day after a current-period approval that granted credits (BR-4.4)', function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $user = customer();
    $request = Membership::factory()->for($user->household)->for(Package::factory()->create())->create();
    app(ApproveMembership::class)->handle($request, admin(), '2026-10', 8, 52000, 4);

    $this->travelTo(Carbon::parse('2026-10-17 00:05'));
    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    expect(CreditLedger::balance($user->household))->toBe(4)
        ->and(CreditEntry::query()->count())->toBe(1);
    Mail::assertNotQueued(MonthlyCreditsReady::class);
});

it('resumes mid-month with nothing granted until the next 1st, keeping the membership in effect (BR-4.9)', function () {
    $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    $user = customer();
    $membership = activeMembership($user, '2026-09', ['grants_from_period' => '2026-11']);
    app(PauseMembership::class)->handle($membership);
    app(ResumeMembership::class)->handle($membership);

    $this->travelTo(Carbon::parse('2026-10-21 00:05'));
    $this->artisan('optifit:monthly-credits')->assertSuccessful();
    expect(CreditEntry::query()->count())->toBe(0)
        ->and($user->household->membershipInEffect()?->is($membership))->toBeTrue();

    $this->travelTo(Carbon::parse('2026-11-01 00:05'));
    $this->artisan('optifit:monthly-credits')->assertSuccessful();
    expect(CreditLedger::balance($user->household))->toBe(8);
});

it('pausing and resuming within the same month changes nothing for a membership already granted (BR-4.9)', function () {
    $this->travelTo(Carbon::parse('2026-10-01 00:05'));
    $user = customer();
    $membership = activeMembership($user, '2026-09');
    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    $this->travelTo(Carbon::parse('2026-10-10'));
    app(PauseMembership::class)->handle($membership);
    app(ResumeMembership::class)->handle($membership);
    $this->artisan('optifit:monthly-credits')->assertSuccessful();

    expect(CreditEntry::query()->count())->toBe(1)
        ->and($membership->fresh()->grants_from_period)->toBe('2026-11')
        ->and($user->household->membershipInEffect()?->is($membership))->toBeTrue();
});

it('does not turn an ending membership into a phantom scheduled one when paused and resumed (BR-4.8, 4.9)', function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $user = customer();
    $membership = activeMembership($user, '2026-09', ['ends_period' => '2026-10']);
    app(PauseMembership::class)->handle($membership);
    app(ResumeMembership::class)->handle($membership);

    expect($user->household->scheduledMembership())->toBeNull()
        ->and($membership->fresh()->isEnding())->toBeTrue();
});
