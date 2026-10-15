<?php

/*
 * A separate PHP process for BookingConcurrencyTest (BR-7.6). It waits for a shared start time, so that several
 * workers reach the household lock together, then books. It prints "ready <time>" when it reaches the barrier and
 * then "booked" or "refused: <reason>".
 *
 * Usage: php book-worker.php <now> <start-at> <household-id> <session-id> <user-id> <member-id,member-id,...>
 */

use App\Actions\Bookings\BookSession;
use App\Exceptions\BookingException;
use App\Models\ClassSession;
use App\Models\Household;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $now, $startAt, $householdId, $sessionId, $userId, $memberIds] = $argv;

Carbon::setTestNow($now);

echo 'ready '.microtime(true)."\n";

while (microtime(true) < (float) $startAt) {
    usleep(500);
}

try {
    app(BookSession::class)->handle(
        Household::query()->findOrFail($householdId),
        ClassSession::query()->findOrFail($sessionId),
        array_map('intval', explode(',', $memberIds)),
        User::query()->findOrFail($userId),
    );
    echo 'booked';
} catch (BookingException $exception) {
    echo 'refused: '.$exception->getMessage();
}
