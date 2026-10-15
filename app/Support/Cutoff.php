<?php

namespace App\Support;

use App\Models\ClassSession;
use App\Models\Settings;
use Illuminate\Support\Carbon;

/**
 * When booking and free cancellation close for a session (BR-7.2), and how far ahead customers may book.
 */
final class Cutoff
{
    /**
     * D2: the session's own override, else the studio default.
     */
    public static function hoursFor(ClassSession $session): int
    {
        return $session->cutoff_hours ?? Settings::current()->default_cutoff_hours;
    }

    public static function at(ClassSession $session): Carbon
    {
        return $session->starts_at->copy()->subHours(self::hoursFor($session));
    }

    public static function hasPassed(ClassSession $session): bool
    {
        return now()->gte(self::at($session));
    }

    /**
     * BR-7.2 item 3: the last moment a customer can book into, which is the end of the shorter of the
     * booking horizon and the current month.
     */
    public static function windowEnd(): Carbon
    {
        $horizon = today()->addDays(Settings::current()->booking_horizon_days - 1)->endOfDay();

        return $horizon->min(now()->endOfMonth());
    }
}
