<?php

namespace App\Console\Commands;

use App\Actions\Credits\ExpireCredits;
use App\Actions\Credits\GrantMonthlyCredits;
use App\Actions\Memberships\EndMembership;
use App\Enums\MembershipStatus;
use App\Mail\MonthlyCreditsReady;
use App\Models\CreditEntry;
use App\Models\Household;
use App\Models\Membership;
use App\Support\Period;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

// BR-5.5: expire last month's credits, end finished memberships, grant this month's credits. Safe to run any number of times.
class RunMonthlyCredits extends Command
{
    protected $signature = 'optifit:monthly-credits';

    protected $description = 'Expire past credits, end finished memberships and grant this month\'s credits (idempotent)';

    public function handle(ExpireCredits $expire, EndMembership $end, GrantMonthlyCredits $grant): int
    {
        $current = Period::current();

        $expired = $this->expirePastCredits($expire, $current);
        $ended = $this->endFinishedMemberships($end, $current);
        $granted = $this->grantCurrentCredits($grant, $current);

        $this->info("Credits for {$current}: expired {$expired}, ended {$ended} membership(s), granted {$granted}.");

        return self::SUCCESS;
    }

    // Step 1: any past period with a positive balance, not only last month, so a missed run self-heals.
    private function expirePastCredits(ExpireCredits $expire, string $current): int
    {
        $expired = 0;

        $leftovers = CreditEntry::query()
            ->select('household_id', 'period')
            ->where('period', '<', $current)
            ->groupBy('household_id', 'period')
            ->havingRaw('SUM(amount) > 0')
            ->get();

        foreach ($leftovers as $leftover) {
            $household = Household::query()->findOrFail($leftover->household_id);
            $expired += $expire->handle($household, $leftover->period) !== null ? 1 : 0;
        }

        return $expired;
    }

    // Step 2
    private function endFinishedMemberships(EndMembership $end, string $current): int
    {
        $ended = 0;

        $finished = Membership::query()
            ->whereIn('status', [MembershipStatus::Active, MembershipStatus::Paused])
            ->where('ends_period', '<', $current)
            ->get();

        foreach ($finished as $membership) {
            $ended += $end->handle($membership) ? 1 : 0;
        }

        return $ended;
    }

    // Step 3: the "credits ready" email goes out only the first time a grant is written.
    private function grantCurrentCredits(GrantMonthlyCredits $grant, string $current): int
    {
        $granted = 0;

        $due = Membership::query()
            ->with(['household.user', 'package'])
            ->dueForGrant($current)
            ->get();

        foreach ($due as $membership) {
            if (! $grant->handle($membership, $current)) {
                continue;
            }

            $granted++;
            Mail::to($membership->household->user->email)->queue(new MonthlyCreditsReady($membership, $current));
        }

        return $granted;
    }
}
