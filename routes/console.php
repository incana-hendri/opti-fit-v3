<?php

use Illuminate\Support\Facades\Schedule;

// docs/07 § Scheduler. cPanel runs `schedule:run` every minute. The 06:00 below-minimum email (A5) arrives in Milestone 3.
Schedule::command('optifit:monthly-credits')->dailyAt('00:05')->timezone('Africa/Johannesburg');
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->dailyAt('03:00')->timezone('Africa/Johannesburg');
Schedule::command('auth:clear-resets')->dailyAt('03:00')->timezone('Africa/Johannesburg');
