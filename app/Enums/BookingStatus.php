<?php

namespace App\Enums;

// BR-7.x. `booked`, `attended` and `no_show` hold a place and are "active" (BR-7.4, 7.5).
enum BookingStatus: string
{
    case Booked = 'booked';
    case Cancelled = 'cancelled';
    case Attended = 'attended';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::NoShow => 'No-show',
            default => ucfirst($this->value),
        };
    }

    /**
     * Statuses that take a place in the session.
     *
     * @return list<self>
     */
    public static function occupying(): array
    {
        return [self::Booked, self::Attended, self::NoShow];
    }

    /**
     * Colour name for Filament badges.
     */
    public function filamentColor(): string
    {
        return match ($this) {
            self::Booked => 'info',
            self::Attended => 'success',
            self::NoShow => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
