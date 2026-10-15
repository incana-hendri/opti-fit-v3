<?php

namespace App\Models;

use App\Enums\Gender;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A person who can attend classes. Family members have no login (D9).
 *
 * @property Gender $gender
 * @property Carbon $date_of_birth
 * @property Carbon|null $archived_at
 * @property int|null $memberships_count only present when loaded with withCount()
 */
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected $fillable = ['first_name', 'last_name', 'date_of_birth', 'gender', 'health_notes'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'is_account_holder' => 'boolean',
            'health_notes' => 'encrypted', // BR-2.3
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function age(): int
    {
        return (int) $this->date_of_birth->diffInYears(today());
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @return BelongsToMany<Membership, $this>
     */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'membership_member');
    }

    // BR-2.5: members that anything refers to can only be archived. Bookings join the check in Milestone 3.
    public function canBeDeleted(): bool
    {
        return ($this->memberships_count ?? $this->memberships()->count()) === 0;
    }
}
