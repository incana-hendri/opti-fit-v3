<?php

namespace App\Models;

use Database\Factories\PackagePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One price tier of a package: the monthly price from `member_count` members up (BR-3.2).
 *
 * @property int $member_count
 * @property int $price_cents
 */
class PackagePrice extends Model
{
    /** @use HasFactory<PackagePriceFactory> */
    use HasFactory;

    protected $fillable = ['member_count', 'price_cents'];

    protected function casts(): array
    {
        return [
            'member_count' => 'integer',
            'price_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
