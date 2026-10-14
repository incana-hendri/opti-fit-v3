<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Support\Period;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A household's subscription to one package for a set of covered members (BR-4.x).
 *
 * @property MembershipStatus $status
 * @property int $monthly_credits
 * @property int $monthly_price_cents
 * @property string|null $starts_period
 * @property string|null $ends_period
 * @property Carbon $requested_at
 * @property Carbon|null $approved_at
 */
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'monthly_credits' => 'integer',
            'monthly_price_cents' => 'integer',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * @return BelongsToMany<Member, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'membership_member');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * BR-4.1: active or paused, started, and not yet ended in $period.
     *
     * @param  Builder<Membership>  $query
     */
    public function scopeInEffect(Builder $query, string $period): void
    {
        $query->whereIn('status', [MembershipStatus::Active, MembershipStatus::Paused])
            ->where('starts_period', '<=', $period)
            ->where(fn (Builder $query) => $query->whereNull('ends_period')->orWhere('ends_period', '>=', $period));
    }

    public function isInEffect(string $period): bool
    {
        return in_array($this->status, [MembershipStatus::Active, MembershipStatus::Paused], true)
            && $this->starts_period !== null
            && $this->starts_period <= $period
            && ($this->ends_period === null || $this->ends_period >= $period);
    }

    // BR-4.8: cancelled by the customer, still usable until the month ends.
    public function isEnding(): bool
    {
        return $this->status === MembershipStatus::Active && $this->ends_period === Period::current();
    }

    // BR-4.7: approved, but only starts next period.
    public function isScheduled(): bool
    {
        return $this->status === MembershipStatus::Active
            && $this->starts_period !== null
            && $this->starts_period > Period::current();
    }

    /**
     * The ledger text for this membership's grant, e.g. "October credits (2× per week, prorated)".
     */
    public function grantDescription(string $period, bool $prorated = false): string
    {
        $frequency = $this->package->sessions_per_week.'× per week'.($prorated ? ', prorated' : '');

        return Period::label($period)." credits ({$frequency})";
    }
}
