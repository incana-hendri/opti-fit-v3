<?php

namespace App\Livewire;

use App\Actions\Memberships\CancelMembership;
use App\Actions\Memberships\UndoMembershipCancellation;
use App\Actions\Memberships\WithdrawMembershipRequest;
use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Models\CreditEntry;
use App\Models\Household;
use App\Models\User;
use App\Support\Period;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * /membership: the current package, a pending request and the credit history (docs/04 § /membership; BR-4.3, 4.8, 5.8).
 */
class MembershipPage extends Component
{
    /** How many periods of history the customer sees: this one and two before it (BR-5.8). */
    private const HISTORY_PERIODS = 3;

    public ?int $withdrawId = null;

    public function confirmCancel(): void
    {
        $this->dispatch('open-sheet', name: 'cancel');
    }

    public function cancel(): void
    {
        try {
            app(CancelMembership::class)->handle($this->household());
            $this->dispatch('toast', message: 'Your membership will end this month.');
        } catch (MembershipException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');
        }

        $this->dispatch('close-sheet', name: 'cancel');
    }

    public function undoCancellation(): void
    {
        try {
            app(UndoMembershipCancellation::class)->handle($this->household());
            $this->dispatch('toast', message: 'Cancellation undone. Your membership carries on.');
        } catch (MembershipException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');
        }
    }

    // BR-2.7: requests are only ever looked up through the customer's own household.
    public function confirmWithdraw(int $membershipId): void
    {
        $this->household()->memberships()->findOrFail($membershipId);
        $this->withdrawId = $membershipId;
        $this->dispatch('open-sheet', name: 'withdraw');
    }

    public function withdraw(): void
    {
        if ($this->withdrawId === null) {
            return;
        }

        $household = $this->household();

        try {
            app(WithdrawMembershipRequest::class)->handle($household, $household->memberships()->findOrFail($this->withdrawId));
            $this->dispatch('toast', message: 'Your request has been withdrawn.');
        } catch (MembershipException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'error');
        }

        $this->withdrawId = null;
        $this->dispatch('close-sheet', name: 'withdraw');
    }

    public function render(): View
    {
        $household = $this->household();
        $current = $household->membershipInEffect();
        $scheduled = $household->scheduledMembership();

        return view('livewire.membership-page', [
            'current' => $current?->load(['package', 'members']),
            'scheduled' => $scheduled?->load(['package', 'members']),
            'pending' => $household->pendingRequest()?->load(['package', 'members']),
            'ending' => $scheduled === null && $current?->isEnding(),
            'canCancel' => $scheduled === null && $current?->status === MembershipStatus::Active && $current->ends_period === null,
            'lastDay' => Period::lastDay(Period::current()),
            'history' => $this->history($household),
        ])->title('Membership');
    }

    /**
     * @return array<string, Collection<int, CreditEntry>> entries per period, newest period first
     */
    private function history(Household $household): array
    {
        $periods = [Period::current()];
        while (count($periods) < self::HISTORY_PERIODS) {
            $periods[] = Period::previous(end($periods));
        }

        $entries = $household->creditEntries()
            ->whereIn('period', $periods)
            ->latest('created_at')->latest('id')
            ->get()
            ->groupBy('period');

        return collect($periods)
            ->filter(fn (string $period) => $entries->has($period))
            ->mapWithKeys(fn (string $period) => [$period => $entries[$period]])
            ->all();
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
