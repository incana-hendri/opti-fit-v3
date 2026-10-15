<?php

use App\Enums\CreditEntryType;
use App\Models\Booking;
use App\Models\CreditEntry;
use App\Models\User;
use App\Support\CreditLedger;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\Process;

// BR-7.6: two real PHP processes book at the same moment against committed data. Exactly one may succeed.
// This file uses DatabaseTruncation (see tests/Pest.php), because a rolled-back test transaction is invisible to other processes.

/**
 * Runs one worker per [user, session, member ids] at the same instant and returns what each decided. It fails loudly if a
 * worker reached the barrier after the start time, because then the bookings ran one after the other and prove nothing.
 *
 * @param  list<array{User, int, list<int>}>  $attempts
 * @return list<string>
 */
function bookTogether(array $attempts): array
{
    $startAt = microtime(true) + 3;
    $environment = collect(['APP_ENV', 'CACHE_STORE', 'DB_DATABASE', 'DB_URL', 'MAIL_MAILER', 'QUEUE_CONNECTION', 'SESSION_DRIVER'])
        ->mapWithKeys(fn (string $name) => [$name => getenv($name)])
        ->filter(fn ($value) => $value !== false)
        ->all();

    $processes = array_map(function (array $attempt) use ($startAt, $environment) {
        [$user, $sessionId, $memberIds] = $attempt;
        $process = new Process(
            [PHP_BINARY, __DIR__.'/book-worker.php', now()->toDateTimeString(), (string) $startAt, (string) $user->household->id, (string) $sessionId, (string) $user->id, implode(',', $memberIds)],
            dirname(__DIR__, 2),
            $environment,
        );
        $process->start();

        return $process;
    }, $attempts);

    return array_map(function (Process $process) use ($startAt) {
        $process->wait();
        [$ready, $result] = array_pad(explode("\n", trim($process->getOutput().$process->getErrorOutput()), 2), 2, '');

        expect($ready)->toStartWith('ready ', "A worker didn't start: {$result}");
        expect((float) substr($ready, 6))->toBeLessThan($startAt, 'A worker reached the barrier too late, so the bookings were not simultaneous.');

        return $result;
    }, $processes);
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));
});

it('lets exactly one of two households take the last place (BR-7.6)', function () {
    $session = classSession(['starts_at' => '2026-10-19 05:00', 'capacity' => 1]);
    $first = bookingCustomer(1);
    $second = bookingCustomer(1);

    $results = bookTogether([
        [$first, $session->id, [$first->household->members->first()->id]],
        [$second, $session->id, [$second->household->members->first()->id]],
    ]);

    expect(collect($results)->filter(fn (string $result) => $result === 'booked'))->toHaveCount(1)
        ->and(collect($results)->filter(fn (string $result) => str_starts_with($result, 'refused: This class is full')))->toHaveCount(1)
        ->and(Booking::query()->count())->toBe(1)
        ->and($session->fresh()->placesLeft())->toBe(0);
});

it('lets exactly one of two bookings spend a household\'s last credit (BR-7.6, 5.4)', function () {
    $user = bookingCustomer(2, 1);
    $one = classSession(['starts_at' => '2026-10-19 05:00']);
    $two = classSession(['starts_at' => '2026-10-20 05:00']);
    [$mia, $liam] = $user->household->members->pluck('id')->all();

    $results = bookTogether([
        [$user, $one->id, [$mia]],
        [$user, $two->id, [$liam]],
    ]);

    expect(collect($results)->filter(fn (string $result) => $result === 'booked'))->toHaveCount(1)
        ->and(collect($results)->filter(fn (string $result) => str_starts_with($result, 'refused: You need 1 credit but have 0')))->toHaveCount(1)
        ->and(CreditLedger::balance($user->household))->toBe(0)
        ->and(CreditEntry::query()->where('type', CreditEntryType::Booking)->count())->toBe(1);
});

it('lets exactly one of two identical requests book a member (BR-7.5, 7.6)', function () {
    $user = bookingCustomer(1, 5);
    $session = classSession(['starts_at' => '2026-10-19 05:00']);
    $member = $user->household->members->first()->id;

    $results = bookTogether([[$user, $session->id, [$member]], [$user, $session->id, [$member]]]);

    expect(collect($results)->filter(fn (string $result) => $result === 'booked'))->toHaveCount(1)
        ->and(Booking::query()->count())->toBe(1)
        ->and(CreditLedger::balance($user->household))->toBe(4);
});
