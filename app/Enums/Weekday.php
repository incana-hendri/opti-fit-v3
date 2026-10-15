<?php

namespace App\Enums;

// BR-8.1: ISO weekdays, 1 = Monday ... 7 = Sunday.
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return $this->name;
    }

    public function shortLabel(): string
    {
        return substr($this->name, 0, 3);
    }

    /**
     * @return array<int, string> ISO number => name, for selects
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $day) => [$day->value => $day->label()])->all();
    }
}
