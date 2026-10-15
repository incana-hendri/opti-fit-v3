<?php

namespace App\Livewire;

use App\Actions\Memberships\RequestMembership;
use App\Exceptions\MembershipException;
use App\Models\Household;
use App\Models\Package;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * /membership/request: pick a package and who it covers (docs/04 § /membership; BR-4.2, 4.7, 2.6).
 */
class RequestForm extends Component
{
    public ?int $packageId = null;

    /** @var list<int|string> */
    public array $memberIds = [];

    public string $note = '';

    public function mount(): void
    {
        $household = $this->household();
        $current = $household->membershipInEffect();
        $requested = request()->integer('package');

        $this->memberIds = $household->activeMembers()->pluck('id')->all();
        $this->packageId = Package::query()->offered()->whereKey($requested ?: $current?->package_id)->value('id');
    }

    public function send(): mixed
    {
        $household = $this->household();

        $this->validate([
            'packageId' => ['required', Rule::exists('packages', 'id')->where('is_active', true)],
            'memberIds' => ['required', 'array', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'packageId.required' => 'Please choose a package.',
            'packageId.exists' => 'That package is no longer available. Please choose another.',
            'memberIds.required' => 'Choose at least one family member.',
            'memberIds.min' => 'Choose at least one family member.',
        ]);

        try {
            app(RequestMembership::class)->handle(
                $household,
                Package::query()->findOrFail($this->packageId),
                array_map('intval', $this->memberIds),
                $this->note,
            );
        } catch (MembershipException $exception) {
            $this->addError('form', $exception->getMessage());

            return null;
        }

        return redirect()->route('membership')
            ->with('toast', ['message' => "Request sent. We'll email you once it's approved.", 'type' => 'success']);
    }

    public function render(): View
    {
        $household = $this->household();
        $packages = Package::query()->offered()->with('prices')->get();
        $members = $household->activeMembers()->oldest()->get();
        $selected = $members->whereIn('id', array_map('intval', $this->memberIds));
        $package = $packages->firstWhere('id', $this->packageId);

        return view('livewire.request-form', [
            'packages' => $packages,
            'members' => $members,
            'package' => $package,
            'count' => $selected->count(),
            'credits' => $package ? $package->credits_per_member * $selected->count() : 0,
            'priceCents' => $package && $selected->isNotEmpty() ? $package->priceFor($selected->count()) : 0,
            'hasEmergencyContact' => $household->hasEmergencyContact(),
            'pending' => $household->pendingRequest(),
            'scheduled' => $household->scheduledMembership(),
            'changing' => $household->membershipInEffect() !== null,
        ])->title('Choose your package');
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
