<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Enums\CreditEntryType;
use App\Enums\MembershipStatus;
use App\Enums\SessionStatus;
use App\Models\ClassSession;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The month's numbers for the dashboard and Reports (docs/05), computed in one place so the two can't disagree.
 * A month is a period `YYYY-MM`; sessions belong to it by their start. Cancelled sessions are left out of the
 * sessions and fill numbers, and their bookings count as cancellations. BR-7.9: a booking still `booked`
 * 24 hours after the start counts as attended.
 */
final class MonthReport
{
    /** @var array{bookings: int, attended: int, noShows: int, lateCancellations: int, cancellations: int, guestPlaces: int, attendanceRate: int|null}|null */
    private ?array $totals = null;

    /** @var list<array{package: string, count: int, income: int}>|null */
    private ?array $memberships = null;

    public function __construct(public readonly string $period) {}

    /**
     * @return array{bookings: int, attended: int, noShows: int, lateCancellations: int, cancellations: int, guestPlaces: int, attendanceRate: int|null}
     */
    public function totals(): array
    {
        return $this->totals ??= $this->computeTotals();
    }

    /**
     * Credits whose ledger period is this month (BR-5.1). Expiry rows carry the period that expired, so the
     * current month shows no expired credits until it has closed (BR-5.5).
     *
     * @return array{granted: int, used: int, adjusted: int, expired: int}
     */
    public function credits(): array
    {
        $sums = DB::table('credit_entries')
            ->where('period', $this->period)
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $sum = fn (CreditEntryType $type): int => (int) ($sums[$type->value] ?? 0);

        return [
            'granted' => $sum(CreditEntryType::Grant),
            'used' => -$sum(CreditEntryType::Booking) - $sum(CreditEntryType::Refund),
            'adjusted' => $sum(CreditEntryType::Adjustment),
            'expired' => -$sum(CreditEntryType::Expiry),
        ];
    }

    /**
     * One row per scheduled session of the month, oldest first. `occupied` is BR-7.4 (bookings that hold a
     * place plus guests); `attended` follows BR-7.9.
     *
     * @return list<array{id: int, startsAt: Carbon, title: string, capacity: int, occupied: int, fillPercent: int, attended: int}>
     */
    public function sessions(): array
    {
        $attendedBefore = now()->subDay();

        return ClassSession::query()
            ->scheduled()
            ->whereBetween('starts_at', $this->range())
            ->withOccupancy()
            ->withCount([
                'bookings as attended_marked_count' => fn (Builder $bookings) => $bookings->where('status', BookingStatus::Attended),
                'bookings as still_booked_count' => fn (Builder $bookings) => $bookings->where('status', BookingStatus::Booked),
            ])
            ->orderBy('starts_at')
            ->get()
            ->map(function (ClassSession $session) use ($attendedBefore): array {
                $settled = $session->starts_at->lte($attendedBefore);
                $occupied = $session->occupied();

                return [
                    'id' => $session->id,
                    'startsAt' => $session->starts_at,
                    'title' => $session->title,
                    'capacity' => $session->capacity,
                    'occupied' => $occupied,
                    'fillPercent' => $session->capacity > 0 ? (int) round($occupied / $session->capacity * 100) : 0,
                    'attended' => (int) $session->getAttribute('attended_marked_count') + ($settled ? (int) $session->getAttribute('still_booked_count') : 0),
                ];
            })
            ->all();
    }

    /**
     * Memberships active in the month, per package, in the packages' display order: active ones, plus cancelled ones
     * whose last period (`ends_period`) is this month or later, because their credits stand until the month ends.
     * The same rule applies inside the month and after it closes. A paused membership is never counted: the data
     * doesn't say when it was paused.
     *
     * @return list<array{package: string, count: int, income: int}>
     */
    public function memberships(): array
    {
        return $this->memberships ??= $this->computeMemberships();
    }

    public function activeMemberships(): int
    {
        return array_sum(array_column($this->memberships(), 'count'));
    }

    /**
     * The sum of the monthly prices of those memberships, in cents.
     */
    public function monthlyIncome(): int
    {
        return array_sum(array_column($this->memberships(), 'income'));
    }

    /**
     * @return list<array{package: string, count: int, income: int}>
     */
    private function computeMemberships(): array
    {
        return Membership::query()
            ->with('package')
            ->where('starts_period', '<=', $this->period)
            ->where(fn (Builder $ends) => $ends->whereNull('ends_period')->orWhere('ends_period', '>=', $this->period))
            ->where(fn (Builder $status) => $status
                ->where('status', MembershipStatus::Active)
                ->orWhere(fn (Builder $cancelled) => $cancelled
                    ->where('status', MembershipStatus::Cancelled)
                    ->whereNotNull('ends_period')))
            ->get()
            ->sortBy(fn (Membership $membership) => [$membership->package->sort_order, $membership->package_id])
            ->groupBy('package_id')
            ->map(fn ($group) => [
                'package' => $group->first()->package->name,
                'count' => $group->count(),
                'income' => (int) $group->sum('monthly_price_cents'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{bookings: int, attended: int, noShows: int, lateCancellations: int, cancellations: int, guestPlaces: int, attendanceRate: int|null}
     */
    private function computeTotals(): array
    {
        $totals = ['bookings' => 0, 'attended' => 0, 'noShows' => 0, 'lateCancellations' => 0, 'cancellations' => 0];

        foreach ($this->bookingGroups() as $group) {
            $count = (int) $group->total;

            if ($group->booking_status === BookingStatus::Cancelled->value) {
                $totals[$group->late ? 'lateCancellations' : 'cancellations'] += $count;

                continue;
            }

            if ($group->session_status !== SessionStatus::Scheduled->value) {
                continue;
            }

            $totals['bookings'] += $count;
            $totals['noShows'] += $group->booking_status === BookingStatus::NoShow->value ? $count : 0;
            $totals['attended'] += match (true) {
                $group->booking_status === BookingStatus::Attended->value => $count,
                $group->booking_status === BookingStatus::Booked->value && $group->settled => $count,
                default => 0,
            };
        }

        $held = $totals['attended'] + $totals['noShows'];

        return $totals + [
            'guestPlaces' => $this->guestPlaces(),
            'attendanceRate' => $held > 0 ? (int) round($totals['attended'] / $held * 100) : null,
        ];
    }

    /**
     * Bookings of the month's sessions, counted by status, cancellation flag, session status and BR-7.9 age.
     *
     * @return Collection<int, stdClass>
     */
    private function bookingGroups(): Collection
    {
        return DB::table('bookings')
            ->join('class_sessions', 'class_sessions.id', '=', 'bookings.class_session_id')
            ->whereBetween('class_sessions.starts_at', $this->range())
            ->selectRaw('bookings.status as booking_status, bookings.late_cancellation as late, class_sessions.status as session_status, (class_sessions.starts_at <= ?) as settled, COUNT(*) as total', [now()->subDay()])
            ->groupBy('booking_status', 'late', 'session_status', 'settled')
            ->get();
    }

    private function guestPlaces(): int
    {
        return (int) DB::table('guest_entries')
            ->join('class_sessions', 'class_sessions.id', '=', 'guest_entries.class_session_id')
            ->whereBetween('class_sessions.starts_at', $this->range())
            ->where('class_sessions.status', SessionStatus::Scheduled->value)
            ->sum('guest_entries.participant_count');
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function range(): array
    {
        return [Period::firstDay($this->period), Period::firstDay($this->period)->endOfMonth()];
    }
}
