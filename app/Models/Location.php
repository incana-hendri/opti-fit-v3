<?php

namespace App\Models;

use App\Exceptions\SessionException;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A venue where classes take place (BR-9.2).
 *
 * @property bool $is_active
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected $fillable = ['name', 'address', 'map_url', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ScheduleTemplate, $this>
     */
    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(ScheduleTemplate::class);
    }

    /**
     * @return HasMany<ClassSession, $this>
     */
    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    // BR-9.2: a location that a future session or an active template still uses can't be archived.
    public function isArchivable(): bool
    {
        return ! $this->scheduleTemplates()->where('is_active', true)->exists()
            && ! $this->classSessions()->where('starts_at', '>=', now())->exists();
    }

    // BR-9.2
    public function archive(): void
    {
        if (! $this->isArchivable()) {
            throw new SessionException("{$this->name} is still used by an active template or an upcoming class, so it can't be archived.");
        }

        $this->update(['is_active' => false]);
    }

    /**
     * The venue shown to the public (footer, timetable): the first active location.
     */
    public static function venue(): ?self
    {
        return static::query()->where('is_active', true)->orderBy('id')->first();
    }
}
