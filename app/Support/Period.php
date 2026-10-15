<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * A period is a calendar month written `YYYY-MM` (docs/01 § Glossary), in the app time zone.
 */
final class Period
{
    public static function current(): string
    {
        return self::fromDate(now());
    }

    public static function next(?string $period = null): string
    {
        return self::fromDate(self::firstDay($period ?? self::current())->addMonth());
    }

    public static function previous(?string $period = null): string
    {
        return self::fromDate(self::firstDay($period ?? self::current())->subMonth());
    }

    public static function fromDate(CarbonInterface $date): string
    {
        return $date->format('Y-m');
    }

    public static function firstDay(string $period): Carbon
    {
        return Carbon::createFromFormat('!Y-m', $period);
    }

    public static function lastDay(string $period): Carbon
    {
        return self::firstDay($period)->endOfMonth()->startOfDay();
    }

    public static function daysIn(string $period): int
    {
        return self::firstDay($period)->daysInMonth;
    }

    /**
     * "October", or "October 2027" when the year isn't the current one.
     */
    public static function label(string $period): string
    {
        $first = self::firstDay($period);

        return $first->year === now()->year ? $first->format('F') : $first->format('F Y');
    }
}
