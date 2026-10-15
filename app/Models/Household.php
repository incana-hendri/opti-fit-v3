<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Support\Period;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An account holder and their family members (BR-2.1). Owns the credit balance.
 *
 * @property Carbon|null $anonymised_at
 * @property int|null $current_balance only present when loaded with withSum(), e.g. in the admin list
 */
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory;

    protected $fillable = ['name', 'emergency_contact_name', 'emergency_contact_phone'];

    protected function casts(): array
    {
        return [
            'anonymised_at' => 'datetime',
        ];
    }

    /**
     * BR-1.6: an account holder asked for deletion and nobody has anonymised the household yet.
     *
     * @param  Builder<Household>  $query
     */
    public function scopeDeletionRequested(Builder $query): void
    {
        $query->whereNull('anonymised_at')
            ->whereHas('user', fn (Builder $user) => $user->whereNotNull('deletion_requested_at'));
    }

    public function isAnonymised(): bool
    {
        return $this->anonymised_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('archived_at');
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<CreditEntry, $this>
     */
    public function creditEntries(): HasMany
    {
        return $this->hasMany(CreditEntry::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The memberships in effect this period (BR-4.1), for eager loading in lists.
     *
     * @return HasMany<Membership, $this>
     */
    public function membershipsInEffect(): HasMany
    {
        return $this->memberships()->inEffect(Period::current());
    }

    /**
     * BR-4.1: the one active or paused membership that covers $period (default: the current one).
     */
    public function membershipInEffect(?string $period = null): ?Membership
    {
        return $this->memberships()->inEffect($period ?? Period::current())->first();
    }

    /**
     * BR-4.7: an approved membership that starts next period, replacing the one in effect.
     */
    public function scheduledMembership(): ?Membership
    {
        return $this->memberships()
            ->where('status', MembershipStatus::Active)
            ->where('starts_period', '>', Period::current())
            ->first();
    }

    /**
     * BR-4.10: the non-archived members covered by the membership in effect now, if it is active (a paused one covers nobody).
     *
     * @return Collection<int, Member>
     */
    public function coveredMembers(): Collection
    {
        $membership = $this->membershipInEffect();

        if ($membership?->status !== MembershipStatus::Active) {
            return new Collection;
        }

        return $membership->members()->whereNull('archived_at')->orderBy('members.id')->get();
    }

    // BR-4.1: at most one membership is requested at a time.
    public function pendingRequest(): ?Membership
    {
        return $this->memberships()->where('status', MembershipStatus::Requested)->first();
    }

    public function hasEmergencyContact(): bool
    {
        return filled($this->emergency_contact_name) && filled($this->emergency_contact_phone);
    }

    /**
     * Onboarding is finished once the household has someone to book (docs/04 § Onboarding).
     */
    public function isOnboarded(): bool
    {
        return $this->activeMembers()->exists();
    }
}
