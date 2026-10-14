<?php

namespace App\Models;

use App\Enums\CreditEntryType;
use Database\Factories\CreditEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One immutable ledger row (BR-5.3). Only `CreditLedger` inserts them.
 *
 * @property string $period
 * @property CreditEntryType $type
 * @property int $amount
 * @property string $description
 * @property Carbon $created_at
 */
class CreditEntry extends Model
{
    /** @use HasFactory<CreditEntryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => CreditEntryType::class,
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Credit entries are immutable (BR-5.3).'));
        static::deleting(fn () => throw new LogicException('Credit entries are immutable (BR-5.3).'));
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
