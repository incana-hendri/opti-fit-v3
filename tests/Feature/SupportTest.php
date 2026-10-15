<?php

use App\Support\Money;
use App\Support\Period;
use App\Support\Proration;
use Illuminate\Support\Carbon;

it('works out periods, rolling over the year', function () {
    $this->travelTo(Carbon::parse('2026-12-31 23:30'));

    expect(Period::current())->toBe('2026-12')
        ->and(Period::next())->toBe('2027-01')
        ->and(Period::previous())->toBe('2026-11')
        ->and(Period::next('2026-01'))->toBe('2026-02')
        ->and(Period::previous('2026-01'))->toBe('2025-12')
        ->and(Period::fromDate(Carbon::parse('2026-02-28')))->toBe('2026-02');
});

it('knows the first day, last day and length of a period', function () {
    expect(Period::firstDay('2026-10')->toDateString())->toBe('2026-10-01')
        ->and(Period::lastDay('2026-10')->toDateString())->toBe('2026-10-31')
        ->and(Period::lastDay('2028-02')->toDateString())->toBe('2028-02-29')
        ->and(Period::daysIn('2026-02'))->toBe(28)
        ->and(Period::daysIn('2026-10'))->toBe(31);
});

it('labels a period by month, adding the year only when it is not this year', function () {
    $this->travelTo(Carbon::parse('2026-10-14'));

    expect(Period::label('2026-10'))->toBe('October')
        ->and(Period::label('2026-11'))->toBe('November')
        ->and(Period::label('2027-01'))->toBe('January 2027');
});

it('formats money as rand with a space for thousands', function (int $cents, string $formatted) {
    expect(Money::format($cents))->toBe($formatted);
})->with([
    [28000, 'R280'],
    [114400, 'R1 144'],
    [12345, 'R123.45'],
    [0, 'R0'],
    [-5000, '-R50'],
]);

it('converts rand input to cents', function (int|float|string $rand, int $cents) {
    expect(Money::toCents($rand))->toBe($cents);
})->with([
    [280, 28000],
    ['12.50', 1250],
    [99.99, 9999],
    ['1144', 114400],
]);

it('prorates the first month credits by remaining days (BR-4.5)', function (int $monthly, string $today, int $expected) {
    expect(Proration::firstMonthCredits($monthly, Carbon::parse($today)))->toBe($expected);
})->with([
    'full month on the 1st' => [8, '2026-10-01', 8],
    'mid month' => [8, '2026-10-16', 4], // 8 × 16/31 = 4.13
    'twelve credits mid month' => [12, '2026-10-16', 6], // 6.19
    'last day is at least 1' => [8, '2026-10-31', 1], // 0.26, minimum 1
    'half rounds up' => [5, '2026-11-16', 3], // 5 × 15/30 = 2.5
    'never above the monthly credits' => [1, '2026-10-01', 1],
]);

it('suggests a prorated price on the same ratio, rounded to the rand (BR-4.5)', function (int $monthlyCents, string $today, int $expected) {
    expect(Proration::suggestedPriceCents($monthlyCents, Carbon::parse($today)))->toBe($expected);
})->with([
    'full month' => [52000, '2026-10-01', 52000],
    'mid month' => [52000, '2026-10-16', 26800], // R268.39
    'last day' => [52000, '2026-10-31', 1700], // R16.77
    'half rand rounds up' => [10050, '2026-11-01', 10100], // R100.50 → R101
]);
