<?php

namespace App\Enums;

// BR-5.2
enum CreditEntryType: string
{
    case Grant = 'grant';
    case Booking = 'booking';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case Expiry = 'expiry';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Heroicon component for the customer's credit history.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Grant => 'heroicon-o-plus-circle',
            self::Booking => 'heroicon-o-ticket',
            self::Refund => 'heroicon-o-arrow-uturn-left',
            self::Adjustment => 'heroicon-o-adjustments-horizontal',
            self::Expiry => 'heroicon-o-clock',
        };
    }
}
