<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use Illuminate\Contracts\View\View;

// docs/04 § Landing: the hero and "This week's timetable" (the next 7 days). The rest of the page arrives in Milestone 4.
class LandingController
{
    private const TIMETABLE_DAYS = 7;

    public function __invoke(): View
    {
        return view('pages.landing', ['sessions' => ClassSession::query()->timetable(self::TIMETABLE_DAYS)->get()]);
    }
}
