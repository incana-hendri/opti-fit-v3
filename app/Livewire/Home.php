<?php

namespace App\Livewire;

use App\Actions\Memberships\UndoMembershipCancellation;
use App\Exceptions\MembershipException;
use App\Models\Household;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\Period;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * /home: the credit card or membership status, and the family (docs/04 § /home; BR-5.8).
 */
class Home extends Component
{
    public function undoCancellation(): void
    {
        try {
            app(UndoMembershipCancellation::class)->handle($this->household());
            $this->dispatch('toast', message: 'Cancellation undone. Your membership carries on.');
        } catch (MembershipException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $household = $this->household();
        $period = Period::current();
        $membership = $household->membershipInEffect();
        $balance = CreditLedger::balance($household);
        $scheduled = $household->scheduledMembership();

        return view('livewire.home', [
            'household' => $household->loadCount('activeMembers'),
            'hasCredits' => $membership !== null || $balance > 0,
            'balance' => $balance,
            'granted' => $this->granted($household, $period),
            'used' => $this->used($household, $period),
            'month' => Period::label($period),
            'expires' => Period::lastDay($period)->format('j M'),
            'ending' => $scheduled === null && $membership?->isEnding(),
            'scheduled' => $scheduled?->load('package'),
            'pending' => $household->pendingRequest()?->load('package'),
        ])->title('Home');
    }

    // Every positive entry this month is credit that came in, and every negative one credit that went out, so balance = granted - used.
    private function granted(Household $household, string $period): int
    {
        return (int) $household->creditEntries()->where('period', $period)->where('amount', '>', 0)->sum('amount');
    }

    private function used(Household $household, string $period): int
    {
        return -(int) $household->creditEntries()->where('period', $period)->where('amount', '<', 0)->sum('amount');
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }

    private function household(): Household
    {
        return $this->user()->household()->firstOrFail();
    }
}
