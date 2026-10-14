<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Mail\AdminMembershipRequest;
use App\Mail\MembershipRequested;
use App\Models\Household;
use App\Models\Membership;
use App\Models\Package;
use App\Models\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// BR-4.1, 4.2, 4.7, 2.6: a customer asks for a package (or a change, while a membership is active). Emails C3 and A2.
class RequestMembership
{
    /**
     * @param  list<int>  $memberIds
     */
    public function handle(Household $household, Package $package, array $memberIds, ?string $note = null): Membership
    {
        $membership = DB::transaction(function () use ($household, $package, $memberIds, $note) {
            $household = Household::whereKey($household->id)->lockForUpdate()->firstOrFail();
            $package = Package::with('prices')->findOrFail($package->id);

            if (! $package->is_active) {
                throw new MembershipException('That package is no longer available. Please choose another.');
            }

            if (! $household->hasEmergencyContact()) {
                throw new MembershipException('Please add an emergency contact before requesting a package.');
            }

            if ($household->pendingRequest() !== null) {
                throw new MembershipException('You already have a package request waiting for approval.');
            }

            $memberIds = array_values(array_unique($memberIds));
            $members = $household->activeMembers()->whereKey($memberIds)->pluck('id');

            if ($members->isEmpty() || $members->count() !== count($memberIds)) {
                throw new MembershipException('Please choose who the package covers from your family members.');
            }

            $membership = $household->memberships()->create([
                'package_id' => $package->id,
                'status' => MembershipStatus::Requested,
                'monthly_credits' => $package->credits_per_member * $members->count(),
                'monthly_price_cents' => $package->priceFor($members->count()),
                'customer_note' => filled($note) ? $note : null,
                'requested_at' => now(),
            ]);
            $membership->members()->attach($members);

            return $membership;
        });

        $membership->load(['household.user', 'package', 'members']);
        Mail::to($membership->household->user->email)->queue(new MembershipRequested($membership));

        $admins = Settings::current()->adminNotificationEmails();
        if ($admins !== []) {
            Mail::to($admins)->queue(new AdminMembershipRequest($membership));
        }

        return $membership;
    }
}
