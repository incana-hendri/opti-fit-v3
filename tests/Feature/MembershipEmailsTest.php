<?php

use App\Mail\AdminMembershipEnding;
use App\Mail\AdminMembershipRequest;
use App\Mail\MembershipApproved;
use App\Mail\MembershipChangeApproved;
use App\Mail\MembershipDeclined;
use App\Mail\MembershipEnding;
use App\Mail\MembershipRequested;
use App\Mail\MonthlyCreditsReady;
use App\Models\Membership;
use App\Models\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
    $this->user = customer(2);
    $this->membership = activeMembership($this->user, '2026-10', ['monthly_credits' => 16, 'monthly_price_cents' => 78000]);
    $this->membership->package->update(['name' => '2× per week']);
});

it('uses the subjects from docs/06', function () {
    $membership = $this->membership;
    $membership->forceFill(['starts_period' => '2026-11', 'ends_period' => '2026-10'])->save();
    $household = $membership->household->name;

    expect((new MembershipRequested($membership))->envelope()->subject)->toBe("We've received your package request")
        ->and((new MembershipApproved($membership, 4))->envelope()->subject)->toBe("You're in! Your OptiFit package is active")
        ->and((new MembershipDeclined($membership))->envelope()->subject)->toBe('About your package request')
        ->and((new MembershipChangeApproved($membership, $membership))->envelope()->subject)->toBe('Your package changes on 1 November')
        ->and((new MonthlyCreditsReady($membership, '2026-10'))->envelope()->subject)->toBe('Your October credits are ready')
        ->and((new MembershipEnding($membership))->envelope()->subject)->toBe('Your membership ends on 31 October')
        ->and((new AdminMembershipRequest($membership))->envelope()->subject)->toBe("Package request: {$household} – 2× per week")
        ->and((new AdminMembershipEnding($membership))->envelope()->subject)->toBe("Membership ending: {$household}");
});

it('queues every membership email (docs/06)', function (string $mailable) {
    expect(is_subclass_of($mailable, ShouldQueue::class))->toBeTrue();
})->with([
    MembershipRequested::class, MembershipApproved::class, MembershipDeclined::class, MembershipChangeApproved::class,
    MonthlyCreditsReady::class, MembershipEnding::class, AdminMembershipRequest::class, AdminMembershipEnding::class,
]);

it('shows the package, members, credits and price in the request emails (C3, A2)', function () {
    $names = $this->membership->members->pluck('first_name')->join(', ', ' and ');

    foreach ([new MembershipRequested($this->membership), new AdminMembershipRequest($this->membership)] as $mail) {
        expect($mail->render())->toContain('2× per week')->toContain($names)->toContain('16 per month')->toContain('R780 per month');
    }
});

it('includes the payment instructions and the first-month credits in the approval email (C4)', function () {
    Settings::query()->first()->update(['payment_instructions' => 'Pay by EFT to FNB 62123456789']);

    $html = (new MembershipApproved($this->membership, 8))->render();

    expect($html)->toContain('Pay by EFT to FNB 62123456789')
        ->toContain('Your first 8 credits are ready to use now')
        ->toContain('Book your first class')
        ->toContain('October');
});

it('promises the credits on the 1st when nothing was granted yet (C4)', function () {
    expect((new MembershipApproved($this->membership, null))->render())->toContain('Your credits arrive on the 1st');
});

it('shows the reason and the contact email in the decline email (C5)', function () {
    $this->membership->update(['decline_reason' => 'We are fully booked.']);

    expect((new MembershipDeclined($this->membership->fresh()))->render())
        ->toContain('We are fully booked.')
        ->toContain('info@opti-fit.co.za');
});

it('shows old and new package and the effective date in the change email (C6)', function () {
    $new = Membership::factory()->for($this->user->household)->for($this->membership->package)->active('2026-11')->create(['monthly_credits' => 24]);

    expect((new MembershipChangeApproved($new, $this->membership))->render())
        ->toContain('1 November')
        ->toContain('16 credits per month')
        ->toContain('24 per month');
});

it('shows the credits, expiry date and book button in the monthly email (C7)', function () {
    expect((new MonthlyCreditsReady($this->membership, '2026-10'))->render())
        ->toContain('16 credits')
        ->toContain('Sat 31 Oct')
        ->toContain('Book now');
});

it('shows the last day and the undo link in the ending emails (C13, A3)', function () {
    $this->membership->update(['ends_period' => '2026-10']);
    $membership = $this->membership->fresh();

    expect((new MembershipEnding($membership))->render())->toContain('Sat 31 Oct')->toContain(route('membership'))
        ->and((new AdminMembershipEnding($membership))->render())->toContain('Sat 31 Oct');
});
