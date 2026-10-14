<?php

namespace App\Actions\Credits;

use App\Enums\CreditEntryType;
use App\Enums\MembershipStatus;
use App\Models\Household;
use App\Models\Membership;
use App\Support\CreditLedger;
use Illuminate\Support\Facades\DB;

// BR-5.5 step 3: an active membership gets its monthly grant, once per period.
class GrantMonthlyCredits
{
    /**
     * @return bool true when the grant was written now, false when it already existed or the membership isn't due
     */
    public function handle(Membership $membership, string $period): bool
    {
        return DB::transaction(function () use ($membership, $period) {
            $household = Household::whereKey($membership->household_id)->lockForUpdate()->firstOrFail();
            $membership = $household->memberships()->with('package')->findOrFail($membership->id);

            if ($membership->status !== MembershipStatus::Active || ! $membership->isInEffect($period)) {
                return false;
            }

            $entry = CreditLedger::write(
                $household,
                $period,
                CreditEntryType::Grant,
                $membership->monthly_credits,
                $membership->grantDescription($period),
                $membership,
                key: "grant:{$membership->id}:{$period}",
            );

            return $entry !== null;
        });
    }
}
