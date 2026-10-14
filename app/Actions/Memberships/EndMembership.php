<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Models\Household;
use App\Models\Membership;
use App\Support\Period;
use Illuminate\Support\Facades\DB;

// BR-4.8, 5.5 step 2: once a membership's last period has passed, its status becomes cancelled.
class EndMembership
{
    public function handle(Membership $membership): bool
    {
        return DB::transaction(function () use ($membership) {
            $household = Household::whereKey($membership->household_id)->lockForUpdate()->firstOrFail();
            $membership = $household->memberships()->findOrFail($membership->id);

            $ended = in_array($membership->status, [MembershipStatus::Active, MembershipStatus::Paused], true)
                && $membership->ends_period !== null
                && $membership->ends_period < Period::current();

            if ($ended) {
                $membership->update(['status' => MembershipStatus::Cancelled]);
            }

            return $ended;
        });
    }
}
