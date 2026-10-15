<?php

namespace App\Actions\Credits;

use App\Enums\CreditEntryType;
use App\Exceptions\CreditException;
use App\Models\CreditEntry;
use App\Models\Household;
use App\Support\CreditLedger;
use App\Support\Period;
use Illuminate\Support\Facades\DB;

// BR-5.5 step 1: a past period's unused credits expire with one -balance entry.
class ExpireCredits
{
    public function handle(Household $household, string $period): ?CreditEntry
    {
        if ($period >= Period::current()) {
            throw new CreditException('Credits only expire once their month is over.');
        }

        return DB::transaction(function () use ($household, $period) {
            $household = Household::whereKey($household->id)->lockForUpdate()->firstOrFail();
            $balance = CreditLedger::balance($household, $period);

            if ($balance <= 0) {
                return null;
            }

            return CreditLedger::write(
                $household,
                $period,
                CreditEntryType::Expiry,
                -$balance,
                'Unused '.Period::label($period).' credits expired',
                key: "expiry:{$household->id}:{$period}",
            );
        });
    }
}
