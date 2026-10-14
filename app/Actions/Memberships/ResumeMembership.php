<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Models\CreditEntry;
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

            $current = Period::current();
            $grantedThisPeriod = CreditEntry::query()->where('idempotency_key', "grant:{$membership->id}:{$current}")->exists();

            // The monthly job grants on any day, so without this it would grant the day after resuming.
            $membership->update([
                'status' => MembershipStatus::Active,
                'starts_period' => $grantedThisPeriod ? $membership->starts_period : max($membership->starts_period, Period::next()),
            ]);

            return $membership;
        });
    }
}
