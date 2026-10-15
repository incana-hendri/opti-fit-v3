<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use Illuminate\Contracts\View\View;

// docs/04 § /timetable: the next 14 days of scheduled classes, read-only.
class TimetableController
{
    private const DAYS = 14;

    public function __invoke(): View
    {
        return view('pages.timetable', ['sessions' => ClassSession::query()->timetable(self::DAYS)->get()]);
    }
}
