<?php

namespace App\Enums;

// Membership lifecycle (BR-4.x).
enum MembershipStatus: string
{
    case Requested = 'requested';
    case Active = 'active';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Declined = 'declined';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Colour name for the `x-badge` component.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Requested => 'orange',
            self::Active => 'success',
            self::Paused => 'info',
            self::Cancelled => 'neutral',
            self::Declined => 'danger',
        };
    }

    /**
     * Colour name for Filament badges.
     */
    public function filamentColor(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Active => 'success',
            self::Paused => 'info',
            self::Cancelled => 'gray',
            self::Declined => 'danger',
        };
    }
}
