<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Models\Household;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;

// BR-4.9: the admin pauses a membership, so the monthly job stops granting for it.
class PauseMembership
{
    public function handle(Membership $membership): Membership
    {
        return DB::transaction(function () use ($membership) {
            $household = Household::whereKey($membership->household_id)->lockForUpdate()->firstOrFail();
            $membership = $household->memberships()->findOrFail($membership->id);

            if ($membership->status !== MembershipStatus::Active) {
                throw new MembershipException('Only an active membership can be paused.');
            }

            $membership->update(['status' => MembershipStatus::Paused]);

            return $membership;
        });
    }
}
