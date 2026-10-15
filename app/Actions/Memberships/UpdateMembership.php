<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Models\Household;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;

// BR-4.9: the admin edits credits, price and covered members. Changes apply from the next grant, so no ledger rows are written.
class UpdateMembership
{
    /**
     * @param  list<int>  $memberIds
     */
    public function handle(Membership $membership, int $monthlyCredits, int $monthlyPriceCents, array $memberIds, ?string $adminNote): Membership
    {
        return DB::transaction(function () use ($membership, $monthlyCredits, $monthlyPriceCents, $memberIds, $adminNote) {
            $household = Household::whereKey($membership->household_id)->lockForUpdate()->firstOrFail();
            $membership = $household->memberships()->findOrFail($membership->id);

            if (! in_array($membership->status, [MembershipStatus::Active, MembershipStatus::Paused], true)) {
                throw new MembershipException('Only an active or paused membership can be edited.');
            }

            if ($monthlyCredits < 1 || $monthlyPriceCents < 0) {
                throw new MembershipException('Credits must be at least 1, and the price can\'t be negative.');
            }

            $memberIds = array_values(array_unique($memberIds));
            $members = $household->activeMembers()->whereKey($memberIds)->pluck('id');

            if ($members->isEmpty() || $members->count() !== count($memberIds)) {
                throw new MembershipException('Choose at least one active member of this household.');
            }

            $membership->update([
                'monthly_credits' => $monthlyCredits,
                'monthly_price_cents' => $monthlyPriceCents,
                'admin_note' => filled($adminNote) ? $adminNote : null,
            ]);
            $membership->members()->sync($members);

            return $membership;
        });
    }
}
