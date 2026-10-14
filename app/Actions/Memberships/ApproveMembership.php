<?php

namespace App\Actions\Memberships;

use App\Enums\CreditEntryType;
use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Mail\MembershipApproved;
use App\Mail\MembershipChangeApproved;
use App\Models\Household;
use App\Models\Membership;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\Period;
use App\Support\Proration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// BR-4.4, 4.5, 4.7: the admin approves a request. Email C4, or C6 for a change of package or members.
class ApproveMembership
{
    /**
     * @param  int|null  $firstMonthCredits  only used for a current-period start; null means the prorated suggestion (BR-4.5)
     */
    public function handle(
        Membership $membership,
        User $admin,
        string $startsPeriod,
        int $monthlyCredits,
        int $monthlyPriceCents,
        ?int $firstMonthCredits = null,
        ?string $adminNote = null,
    ): Membership {
        [$approved, $previous, $granted] = DB::transaction(function () use (
            $membership, $admin, $startsPeriod, $monthlyCredits, $monthlyPriceCents, $firstMonthCredits, $adminNote,
        ) {
            $household = Household::whereKey($membership->household_id)->lockForUpdate()->firstOrFail();
            $approved = $household->memberships()->with('package')->findOrFail($membership->id);
            $current = Period::current();

            if ($approved->status !== MembershipStatus::Requested) {
                throw new MembershipException('Only a requested membership can be approved.');
            }

            if ($monthlyCredits < 1 || $monthlyPriceCents < 0 || ($firstMonthCredits ?? 0) < 0) {
                throw new MembershipException('Credits must be at least 1, and the price and first-month credits can\'t be negative.');
            }

            if ($household->scheduledMembership() !== null) {
                throw new MembershipException('This household already has a package starting next month. Cancel that one first.');
            }

            // BR-4.7: while a membership is in effect this is a change, and it never swaps mid-month.
            $previous = $household->membershipInEffect($current);
            $startsPeriod = $previous !== null ? Period::next() : $startsPeriod;

            if (! in_array($startsPeriod, [$current, Period::next()], true)) {
                throw new MembershipException('A membership can only start this month or next month.');
            }

            $previous?->update(['ends_period' => $current]);
            $approved->update([
                'status' => MembershipStatus::Active,
                'starts_period' => $startsPeriod,
                'monthly_credits' => $monthlyCredits,
                'monthly_price_cents' => $monthlyPriceCents,
                'admin_note' => filled($adminNote) ? $adminNote : null,
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ]);

            $granted = $startsPeriod === $current
                ? $this->grantFirstMonth($household, $approved, $admin, $firstMonthCredits)
                : null;

            return [$approved, $previous, $granted];
        });

        $approved->load(['household.user', 'package', 'members']);
        $email = $approved->household->user->email;
        Mail::to($email)->queue($previous !== null
            ? new MembershipChangeApproved($approved, $previous)
            : new MembershipApproved($approved, $granted));

        return $approved;
    }

    // The key makes a repeated call harmless (BR-4.4); the status check above already stops it.
    private function grantFirstMonth(Household $household, Membership $membership, User $admin, ?int $firstMonthCredits): ?int
    {
        $current = Period::current();
        $credits = $firstMonthCredits ?? Proration::firstMonthCredits($membership->monthly_credits, now());

        if ($credits === 0) {
            return null;
        }

        $entry = CreditLedger::write(
            $household,
            $current,
            CreditEntryType::Grant,
            $credits,
            $membership->grantDescription($current, prorated: $credits < $membership->monthly_credits),
            $membership,
            key: "grant:{$membership->id}:{$current}",
            by: $admin,
        );

        return $entry?->amount;
    }
}
