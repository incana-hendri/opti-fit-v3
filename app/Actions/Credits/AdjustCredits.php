<?php

namespace App\Actions\Credits;

use App\Enums\CreditEntryType;
use App\Exceptions\CreditException;
use App\Models\CreditEntry;
use App\Models\Household;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\Period;
use Illuminate\Support\Facades\DB;

// BR-6.5: the admin adds or removes credits in the current period, with a reason. Never below zero (BR-5.4).
class AdjustCredits
{
    public function handle(Household $household, int $amount, string $reason, User $admin): CreditEntry
    {
        if ($amount === 0) {
            throw new CreditException('Enter a number of credits other than 0.');
        }

        if (trim($reason) === '') {
            throw new CreditException('Please give a reason for the adjustment.');
        }

        return DB::transaction(function () use ($household, $amount, $reason, $admin) {
            $household = Household::whereKey($household->id)->lockForUpdate()->firstOrFail();
            $balance = CreditLedger::balance($household);

            if ($balance + $amount < 0) {
                throw new CreditException("The balance can't go below zero. {$household->name} has {$balance} ".str('credit')->plural($balance).'.');
            }

            return CreditLedger::write(
                $household,
                Period::current(),
                CreditEntryType::Adjustment,
                $amount,
                'Adjustment: '.trim($reason),
                by: $admin,
            ) ?? throw new CreditException('The adjustment could not be saved.');
        });
    }
}
