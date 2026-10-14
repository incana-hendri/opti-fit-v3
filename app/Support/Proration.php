<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The one proration formula (BR-4.5), in integer arithmetic so half-up rounding has no float edge cases.
 */
final class Proration
{
    /**
     * Suggested first-month credits: monthly × remaining days / days in month, rounded half up, min 1, max monthly.
     */
    public static function firstMonthCredits(int $monthlyCredits, CarbonInterface $today): int
    {
        [$remaining, $days] = self::ratio($today);
        $prorated = intdiv(2 * $monthlyCredits * $remaining + $days, 2 * $days);

        return min($monthlyCredits, max(1, $prorated));
    }

    /**
     * Suggested prorated price on the same ratio, rounded to the rand. Shown to the admin only.
     */
    public static function suggestedPriceCents(int $monthlyPriceCents, CarbonInterface $today): int
    {
        [$remaining, $days] = self::ratio($today);
        $rand = 100;

        return intdiv(2 * $monthlyPriceCents * $remaining + $days * $rand, 2 * $days * $rand) * $rand;
    }

    /**
     * @return array{int, int} remaining days (today through month end, inclusive) and days in the month
     */
    private static function ratio(CarbonInterface $today): array
    {
        return [$today->daysInMonth - $today->day + 1, $today->daysInMonth];
    }
}
