<?php

namespace App\Enums;

// BR-7.1
enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Colour name for Filament badges.
     */
    public function filamentColor(): string
    {
        return match ($this) {
            self::Scheduled => 'success',
            self::Cancelled => 'gray',
        };
    }
}
