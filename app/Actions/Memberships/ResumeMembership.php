<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Models\Household;
use App\Models\Membership;
use App\Support\Period;
use Illuminate\Support\Facades\DB;

// BR-4.9: the admin resumes a paused membership. Grants resume on the next 1st, never mid-month.
class ResumeMembership
{
    public function handle(Membership $membership): Membership
    {
        return DB::transaction(function () use ($membership) {
            $household = Household::whereKey($membership->household_id)->lockForUpdate()->firstOrFail();
            $membership = $household->memberships()->findOrFail($membership->id);

            if ($membership->status !== MembershipStatus::Paused) {
                throw new MembershipException('Only a paused membership can be resumed.');
            }

            // The monthly job grants on any day, so resuming mid-month must not make a grant due before the next 1st.
            $membership->update([
                'status' => MembershipStatus::Active,
                'grants_from_period' => max((string) $membership->grants_from_period, Period::next()),
            ]);

            return $membership;
        });
    }
}
