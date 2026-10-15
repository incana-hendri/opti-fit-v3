<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Mail\AdminMembershipEnding;
use App\Mail\MembershipEnding;
use App\Models\Household;
use App\Models\Membership;
use App\Models\Settings;
use App\Support\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// BR-4.8: a customer cancels their active membership. It ends with this period. Emails C13 and A3.
class CancelMembership
{
    public function handle(Household $household): Membership
    {
        $membership = DB::transaction(function () use ($household) {
            $household = Household::whereKey($household->id)->lockForUpdate()->firstOrFail();
            $membership = $household->membershipInEffect();

            if ($membership === null || $membership->status !== MembershipStatus::Active) {
                throw new MembershipException('You don\'t have an active membership to cancel. Please contact the studio.');
            }

            if ($household->scheduledMembership() !== null) {
                throw new MembershipException('Your package is already changing next month. Please contact the studio if you want to stop instead.');
            }

            if ($membership->ends_period !== null) {
                throw new MembershipException('Your membership is already ending this month.');
            }

            $membership->update(['ends_period' => Period::current()]);

            return $membership;
        });

        $membership->load(['household.user', 'package', 'members']);
        Mail::to($membership->household->user->email)->queue(new MembershipEnding($membership));

        $admins = Settings::current()->adminNotificationEmails();
        if ($admins !== []) {
            Mail::to($admins)->queue(new AdminMembershipEnding($membership));
        }

        return $membership;
    }
}
