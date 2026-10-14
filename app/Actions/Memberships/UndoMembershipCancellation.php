<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Models\Household;
use App\Models\Membership;
use App\Support\Period;
use Illuminate\Support\Facades\DB;

// BR-4.8: before the month ends the customer can undo their cancellation, which clears ends_period.
class UndoMembershipCancellation
{
    public function handle(Household $household): Membership
    {
        return DB::transaction(function () use ($household) {
            $household = Household::whereKey($household->id)->lockForUpdate()->firstOrFail();
            $membership = $household->membershipInEffect();

            if ($membership === null || $membership->status !== MembershipStatus::Active || $membership->ends_period !== Period::current()) {
                throw new MembershipException('There\'s no cancellation to undo.');
            }

            // BR-4.7: the same ends_period is what hands over to an approved package change.
            if ($household->scheduledMembership() !== null) {
                throw new MembershipException('Your package is changing next month, so this can\'t be undone.');
            }

            $membership->update(['ends_period' => null]);

            return $membership;
        });
    }
}
