<?php

namespace App\Filament\Widgets;

use App\Support\Money;
use App\Support\MonthReport;
use App\Support\Period;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

// docs/05 § Dashboard 3: the current month so far. The numbers come from MonthReport, like the Reports page.
class ThisMonth extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|array|null $columns = ['default' => 2, 'md' => 4];

    protected function getHeading(): string
    {
        return Period::label(Period::current()).' so far';
    }

    protected function getStats(): array
    {
        $report = new MonthReport(Period::current());
        $totals = $report->totals();
        $credits = $report->credits();

        return [
            Stat::make('Active memberships', $report->activeMemberships()),
            Stat::make('Expected monthly income', Money::format($report->monthlyIncome()))
                ->description('From active memberships'),
            Stat::make('Bookings', $totals['bookings']),
            Stat::make('Attendance rate', $totals['attendanceRate'] === null ? '—' : $totals['attendanceRate'].'%')
                ->description('Attended of attended and no-shows'),
            Stat::make('Credits granted', $credits['granted']),
            Stat::make('Credits used', $credits['used']),
            Stat::make('Credits expired', $credits['expired'])
                ->description('Counted when the month closes'),
        ];
    }
}
