<?php

use App\Enums\CreditEntryType;
use App\Models\CreditEntry;
use App\Support\CreditLedger;
use Illuminate\Support\Carbon;

it('sums the entries of one period into the balance (BR-5.1)', function () {
    $this->travelTo(Carbon::parse('2026-10-14'));
    $household = customer()->household;
    CreditEntry::factory()->for($household)->create(['amount' => 8]);
    CreditEntry::factory()->for($household)->ofType(CreditEntryType::Booking, -3)->create();
    CreditEntry::factory()->for($household)->forPeriod('2026-09')->create(['amount' => 5]);
    CreditEntry::factory()->create(['amount' => 99]); // someone else's household

    expect(CreditLedger::balance($household))->toBe(5)
        ->and(CreditLedger::balance($household, '2026-10'))->toBe(5)
        ->and(CreditLedger::balance($household, '2026-09'))->toBe(5)
        ->and(CreditLedger::balance($household, '2026-08'))->toBe(0);
});

it('refuses to update a credit entry (BR-5.3)', function () {
    $entry = CreditEntry::factory()->create();

    expect(fn () => $entry->update(['amount' => 100]))->toThrow(LogicException::class, 'immutable');
    expect($entry->fresh()->amount)->toBe(8);
});

it('refuses to delete a credit entry (BR-5.3)', function () {
    $entry = CreditEntry::factory()->create();

    expect(fn () => $entry->delete())->toThrow(LogicException::class, 'immutable');
    expect(CreditEntry::query()->count())->toBe(1);
});

it('has no updated_at column (docs/03)', function () {
    $entry = CreditEntry::factory()->create();

    expect($entry->created_at)->not->toBeNull()
        ->and(array_key_exists('updated_at', $entry->getAttributes()))->toBeFalse();
});

it('rejects an entry of 0 credits (docs/03)', function () {
    $household = customer()->household;

    expect(fn () => CreditLedger::write($household, '2026-10', CreditEntryType::Adjustment, 0, 'Nothing'))
        ->toThrow(InvalidArgumentException::class);
    expect(CreditEntry::query()->count())->toBe(0);
});

it('writes an entry with its membership, creator and description', function () {
    $user = customer();
    $membership = activeMembership($user);
    $admin = admin();

    $entry = CreditLedger::write($user->household, '2026-10', CreditEntryType::Grant, 8, 'October credits', $membership, by: $admin);

    expect($entry->type)->toBe(CreditEntryType::Grant)
        ->and($entry->amount)->toBe(8)
        ->and($entry->membership_id)->toBe($membership->id)
        ->and($entry->created_by)->toBe($admin->id)
        ->and($entry->description)->toBe('October credits');
});

it('writes an entry with an idempotency key only once', function () {
    $household = customer()->household;

    $first = CreditLedger::write($household, '2026-10', CreditEntryType::Grant, 8, 'October credits', key: 'grant:1:2026-10');
    $second = CreditLedger::write($household, '2026-10', CreditEntryType::Grant, 8, 'October credits', key: 'grant:1:2026-10');

    expect($first)->toBeInstanceOf(CreditEntry::class)
        ->and($second)->toBeNull()
        ->and(CreditEntry::query()->count())->toBe(1);
});
