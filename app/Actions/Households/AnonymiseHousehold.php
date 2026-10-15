<?php

namespace App\Actions\Households;

use App\Actions\Memberships\CancelMembershipNow;
use App\Actions\Memberships\WithdrawMembershipRequest;
use App\Enums\BookingStatus;
use App\Enums\MembershipStatus;
use App\Exceptions\AccountException;
use App\Models\ClassSession;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// BR-9.6 (POPIA erasure), BR-1.6: scrub the household's personal data, end its login, membership and future bookings. The ledger and past bookings stay.
class AnonymiseHousehold
{
    public function __construct(
        private readonly CancelMembershipNow $cancelMembership,
        private readonly WithdrawMembershipRequest $withdrawRequest,
    ) {}

    public function handle(Household $household, User $by): Household
    {
        return DB::transaction(function () use ($household, $by) {
            $household = Household::whereKey($household->id)->lockForUpdate()->firstOrFail();

            if ($household->isAnonymised()) {
                throw new AccountException('This household has already been anonymised.');
            }

            $this->scrubUser($household->user);
            $this->scrubMembers($household);
            $this->endMemberships($household);
            $this->cancelFutureBookings($household, $by);

            $household->forceFill([
                'name' => "Household {$household->id}",
                'emergency_contact_name' => null,
                'emergency_contact_phone' => null,
                'anonymised_at' => now(),
            ])->save();

            return $household;
        });
    }

    // The login ends: deactivated, a password nobody knows, and no session or reset link left over.
    private function scrubUser(User $user): void
    {
        $oldEmail = $user->email;

        $user->forceFill([
            'first_name' => 'Deleted',
            'last_name' => "User {$user->id}",
            'email' => "deleted-{$user->id}@invalid.local",
            'phone' => null,
            'date_of_birth' => $user->date_of_birth?->copy()->startOfYear(),
            'marketing_opt_in' => false,
            'password' => Str::random(60),
            'remember_token' => null,
            'deactivated_at' => $user->deactivated_at ?? now(),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table'))->where('user_id', $user->id)->delete();
        }
    }

    // Archived too, so nobody can book them again; their bookings stay for the reports.
    private function scrubMembers(Household $household): void
    {
        $household->members->each(fn (Member $member) => $member->forceFill([
            'first_name' => 'Member',
            'last_name' => (string) $member->id,
            'date_of_birth' => $member->date_of_birth->copy()->startOfYear(),
            'health_notes' => null,
            'archived_at' => $member->archived_at ?? now(),
        ])->save());
    }

    // The active or paused membership ends now; a pending request is withdrawn. Nobody is emailed.
    private function endMemberships(Household $household): void
    {
        foreach ($household->memberships()->get() as $membership) {
            match ($membership->status) {
                MembershipStatus::Active, MembershipStatus::Paused => $this->cancelMembership->handle($membership),
                MembershipStatus::Requested => $this->withdrawRequest->handle($household, $membership),
                default => null,
            };
        }

        // The customer's own words may hold personal details; the admin's notes are payment records and stay.
        $household->memberships()->update(['customer_note' => null]);
    }

    // No refund: nothing is owed to a household that asked to be erased (BR-9.6). Household first, then the sessions in id order (BR-7.6).
    private function cancelFutureBookings(Household $household, User $by): void
    {
        $bookings = $household->bookings()
            ->where('status', BookingStatus::Booked)
            ->whereHas('classSession', fn ($session) => $session->where('starts_at', '>', now()));

        ClassSession::query()->whereIn('id', (clone $bookings)->select('class_session_id'))->orderBy('id')->lockForUpdate()->get();

        $bookings->update([
            'status' => BookingStatus::Cancelled,
            'late_cancellation' => false,
            'cancelled_at' => now(),
            'cancelled_by' => $by->id,
        ]);
    }
}
