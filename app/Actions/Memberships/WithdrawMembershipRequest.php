<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Models\Household;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;

// BR-4.3: a customer withdraws their own requested membership, which deletes it.
class WithdrawMembershipRequest
{
    public function handle(Household $household, Membership $membership): void
    {
        DB::transaction(function () use ($household, $membership) {
            $household = Household::whereKey($household->id)->lockForUpdate()->firstOrFail();
            $request = $household->memberships()->whereKey($membership->id)->first();

            if ($request === null || $request->status !== MembershipStatus::Requested) {
                throw new MembershipException('This request can no longer be withdrawn.');
            }

            $request->delete();
        });
    }
}
