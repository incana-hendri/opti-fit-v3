<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Mail\MembershipDeclined;
use App\Models\Household;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// BR-4.6: the admin declines a request with a reason. Email C5.
class DeclineMembership
{
    public function handle(Membership $membership, string $reason): Membership
    {
        $declined = DB::transaction(function () use ($membership, $reason) {
            $household = Household::whereKey($membership->household_id)->lockForUpdate()->firstOrFail();
            $declined = $household->memberships()->findOrFail($membership->id);

            if ($declined->status !== MembershipStatus::Requested) {
                throw new MembershipException('Only a requested membership can be declined.');
            }

            if (trim($reason) === '') {
                throw new MembershipException('Please give a reason for declining.');
            }

            $declined->update(['status' => MembershipStatus::Declined, 'decline_reason' => trim($reason)]);

            return $declined;
        });

        $declined->load(['household.user', 'package']);
        Mail::to($declined->household->user->email)->queue(new MembershipDeclined($declined));

        return $declined;
    }
}
