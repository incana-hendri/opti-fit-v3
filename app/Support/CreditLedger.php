<?php

namespace App\Support;

use App\Enums\CreditEntryType;
use App\Models\CreditEntry;
use App\Models\Household;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The only code that inserts credit entries (AGENTS.md § Architecture). A balance is always a SUM, never stored (BR-5.1).
 */
final class CreditLedger
{
    /**
     * BR-5.1: the balance for a period (default: the current one).
     */
    public static function balance(Household $household, ?string $period = null): int
    {
        return (int) $household->creditEntries()->where('period', $period ?? Period::current())->sum('amount');
    }

    /**
     * Appends one entry. Call it inside the caller's transaction, holding the household lock.
     * With an idempotency key it returns null instead of writing a second entry (BR-5.5, 5.7).
     */
    public static function write(
        Household $household,
        string $period,
        CreditEntryType $type,
        int $amount,
        string $description,
        ?Membership $membership = null,
        ?int $bookingId = null,
        ?string $key = null,
        ?User $by = null,
    ): ?CreditEntry {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Credit entries are written inside the caller\'s transaction.');
        }

        if ($amount === 0) {
            throw new InvalidArgumentException('A credit entry never has an amount of 0.');
        }

        if ($key !== null && CreditEntry::query()->where('idempotency_key', $key)->exists()) {
            return null;
        }

        try {
            return $household->creditEntries()->create([
                'period' => $period,
                'type' => $type,
                'amount' => $amount,
                'membership_id' => $membership?->id,
                'booking_id' => $bookingId,
                'description' => $description,
                'idempotency_key' => $key,
                'created_by' => $by?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another request wrote the same key between the check and the insert.
            return null;
        }
    }
}
