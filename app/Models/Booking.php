<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\CreditEntryType;
use App\Enums\SessionStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One member's place in one session (BR-7.x).
 *
 * @property BookingStatus $status
 * @property bool $credit_charged
 * @property bool $late_cancellation
 * @property Carbon|null $cancelled_at
 * @property bool|null $refunded only present after withRefunded()
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'credit_charged' => 'boolean',
            'late_cancellation' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ClassSession, $this>
     */
    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasMany<CreditEntry, $this>
     */
    public function creditEntries(): HasMany
    {
        return $this->hasMany(CreditEntry::class);
    }

    /**
     * Adds `refunded`, so lists can show "Return credit" without a query per row.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeWithRefunded(Builder $query): void
    {
        $query->withExists(['creditEntries as refunded' => fn (Builder $entries) => $entries->where('type', CreditEntryType::Refund)]);
    }

    /**
     * BR-7.4: the statuses that hold a place.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeOccupying(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::occupying());
    }

    /**
     * The customer's status label for /bookings (docs/04) and the badge colour for `x-badge`.
     * Needs `classSession` and `cancelledBy` loaded.
     *
     * @return array{string, string}
     */
    public function customerLabel(): array
    {
        $byStudio = $this->classSession->status === SessionStatus::Cancelled || $this->cancelledBy?->isAdmin();

        return match (true) {
            $this->status === BookingStatus::Cancelled && $byStudio => ['Cancelled by studio', 'neutral'],
            $this->status === BookingStatus::Cancelled && $this->late_cancellation => ['Late cancel', 'orange'],
            $this->status === BookingStatus::Cancelled => ['Cancelled', 'neutral'],
            $this->status === BookingStatus::Attended => ['Attended', 'success'],
            $this->status === BookingStatus::NoShow => ['No-show', 'danger'],
            // BR-7.9: still booked 24 hours after the start counts as attended.
            $this->classSession->starts_at->addDay()->isPast() => ['Attended', 'success'],
            default => ['Booked', 'info'],
        };
    }

    /**
     * A household's bookings that are still on: booked, whether or not the class has happened yet.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeBookedFor(Builder $query, Household $household): void
    {
        $query->where('household_id', $household->id)->where('status', BookingStatus::Booked);
    }

    /**
     * A household's bookings that belong under "Past": cancelled or marked, or whose class has started.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopePastFor(Builder $query, Household $household): void
    {
        $query->where('household_id', $household->id)->where(fn (Builder $past) => $past
            ->where('status', '!=', BookingStatus::Booked)
            ->orWhereHas('classSession', fn (Builder $session) => $session->where('starts_at', '<=', now())));
    }

    // BR-5.7: at most one refund per booking.
    public function isRefunded(): bool
    {
        return $this->refunded ?? $this->creditEntries()->where('type', CreditEntryType::Refund)->exists();
    }

    // BR-6.1: a charged booking that isn't on the timetable any more and didn't get its credit back.
    public function canReturnCredit(): bool
    {
        return $this->credit_charged
            && $this->status !== BookingStatus::Booked
            && ! $this->isRefunded();
    }
}
