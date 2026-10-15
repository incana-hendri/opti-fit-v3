<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Models\Household;
use App\Models\Membership;
use App\Support\Period;
use Illuminate\Support\Facades\DB;

// BR-4.9: the admin cancels a membership now. Credits already granted stay until month end (the ledger is untouched).
class CancelMembershipNow
{
    public function handle(Membership $membership): Membership
    {
        return DB::transaction(function () use ($membership) {
            $household = Household::whereKey($membership->household_id)->lockForUpdate()->firstOrFail();
            $membership = $household->memberships()->findOrFail($membership->id);

            if (! in_array($membership->status, [MembershipStatus::Active, MembershipStatus::Paused], true)) {
                throw new MembershipException('Only an active or paused membership can be cancelled.');
            }

            $started = $membership->starts_period <= Period::current();
            $membership->update([
                'status' => MembershipStatus::Cancelled,
                'ends_period' => $started ? ($membership->ends_period ?? Period::current()) : $membership->ends_period,
            ]);

            return $membership;
        });
    }
}
