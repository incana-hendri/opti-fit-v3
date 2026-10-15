<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\SessionStatus;
use App\Support\Cutoff;
use Database\Factories\ClassSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One class on one day (BR-7.1). Named ClassSession because Laravel owns `sessions`.
 *
 * @property Carbon $starts_at
 * @property int $duration_minutes
 * @property int $capacity
 * @property int|null $min_participants
 * @property int|null $cutoff_hours
 * @property SessionStatus $status
 * @property int|null $occupying_bookings_count only present after withOccupancy()
 * @property int|string|null $guest_places only present after withOccupancy()
 */
class ClassSession extends Model
{
    /** @use HasFactory<ClassSessionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'duration_minutes' => 'integer',
            'capacity' => 'integer',
            'min_participants' => 'integer',
            'cutoff_hours' => 'integer',
            'status' => SessionStatus::class,
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
     * @return BelongsTo<ScheduleTemplate, $this>
     */
    public function scheduleTemplate(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * @return HasMany<GuestEntry, $this>
     */
    public function guestEntries(): HasMany
    {
        return $this->hasMany(GuestEntry::class);
    }

    /**
     * @param  Builder<ClassSession>  $query
     */
    public function scopeScheduled(Builder $query): void
    {
        $query->where('status', SessionStatus::Scheduled);
    }

    /**
     * Adds the counts occupied() needs, so lists don't query per row (BR-7.4).
     *
     * @param  Builder<ClassSession>  $query
     */
    public function scopeWithOccupancy(Builder $query): void
    {
        $query->withCount(['bookings as occupying_bookings_count' => fn (Builder $bookings) => $bookings->whereIn('status', BookingStatus::occupying())])
            ->withSum('guestEntries as guest_places', 'participant_count');
    }

    /**
     * The public timetable: scheduled classes from now until the end of the day $days - 1 days ahead (docs/04).
     *
     * @param  Builder<ClassSession>  $query
     */
    public function scopeTimetable(Builder $query, int $days): void
    {
        $query->scheduled()
            ->where('starts_at', '>=', now())
            ->where('starts_at', '<=', today()->addDays($days - 1)->endOfDay())
            ->with('location')
            ->withOccupancy()
            ->orderBy('starts_at');
    }

    public function endsAt(): Carbon
    {
        return $this->starts_at->copy()->addMinutes($this->duration_minutes);
    }

    public function isScheduled(): bool
    {
        return $this->status === SessionStatus::Scheduled;
    }

    public function isPast(): bool
    {
        return $this->starts_at->isPast();
    }

    public function cutoffAt(): Carbon
    {
        return Cutoff::at($this);
    }

    /**
     * BR-7.4: bookings that hold a place plus the guests' participants.
     */
    public function occupied(): int
    {
        return $this->occupyingBookings() + $this->guestPlaces();
    }

    // BR-7.4: negative after an over-capacity admin booking.
    public function placesLeft(): int
    {
        return $this->capacity - $this->occupied();
    }

    // Below the studio's minimum, shown to admins only (BR-7.1).
    public function isBelowMinimum(): bool
    {
        return $this->min_participants !== null && $this->occupied() < $this->min_participants;
    }

    private function occupyingBookings(): int
    {
        return $this->occupying_bookings_count
            ?? $this->bookings()->whereIn('status', BookingStatus::occupying())->count();
    }

    private function guestPlaces(): int
    {
        // withSum() gives null for no guests, which still counts as loaded.
        return (int) (array_key_exists('guest_places', $this->attributes) ? $this->guest_places : $this->guestEntries()->sum('participant_count'));
    }
}
