<?php

namespace App\Models;

use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A product the studio sells, e.g. "2× per week" (BR-3.1).
 *
 * @property int $sessions_per_week
 * @property int $credits_per_member
 * @property bool $is_active
 * @property int $sort_order
 * @property Collection<int, PackagePrice> $prices
 */
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'sessions_per_week', 'credits_per_member', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'sessions_per_week' => 'integer',
            'credits_per_member' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<PackagePrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PackagePrice::class)->orderBy('member_count');
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Active packages in display order (BR-3.3).
     *
     * @param  Builder<Package>  $query
     */
    public function scopeOffered(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * BR-3.2: the tier with the largest member_count <= $members, so the top tier means "N or more".
     */
    public function priceFor(int $members): int
    {
        return $this->prices
            ->where('member_count', '<=', $members)
            ->sortByDesc('member_count')
            ->firstOrFail()
            ->price_cents;
    }

    /**
     * BR-3.4: a package can be deleted only if no membership ever referenced it.
     */
    public function isDeletable(): bool
    {
        return ! $this->memberships()->exists();
    }
}
