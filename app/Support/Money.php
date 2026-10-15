<?php

namespace App\Support;

/**
 * Money is integer cents in ZAR (docs/07): formatted as "R280" or "R1 144", cents only when not whole rand.
 */
final class Money
{
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);
        $decimals = $cents % 100 === 0 ? 0 : 2;

        return $sign.'R'.number_format($cents / 100, $decimals, '.', ' ');
    }

    public static function toCents(int|float|string $rand): int
    {
        return (int) round((float) $rand * 100);
    }
}
