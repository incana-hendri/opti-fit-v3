<?php

namespace App\Models;

use App\Enums\Weekday;
use Database\Factories\ScheduleTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A weekly slot that "Generate sessions" turns into class sessions (BR-8.1).
 *
 * @property Weekday $weekday
 * @property string $start_time HH:MM:SS
 * @property int $duration_minutes
 * @property int $capacity
 * @property int|null $min_participants
 * @property int|null $cutoff_hours
 * @property bool $is_active
 */
class ScheduleTemplate extends Model
{
    /** @use HasFactory<ScheduleTemplateFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
            'duration_minutes' => 'integer',
            'capacity' => 'integer',
            'min_participants' => 'integer',
            'cutoff_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return HasMany<ClassSession, $this>
     */
    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }
}
