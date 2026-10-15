<?php

namespace App\Filament\Pages;

use App\Support\Money;
use App\Support\MonthReport;
use App\Support\Period;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

// docs/05 § Reports: one page, a month picker (current month by default) and a CSV of the sessions table.
class Reports extends Page
{
    private const MONTHS_BACK = 12;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected string $view = 'filament.pages.reports';

    #[Url(as: 'month')]
    public string $month = '';

    public function mount(): void
    {
        $this->month = $this->period();
    }

    /**
     * The chosen month, or the current one when the query string holds something else.
     */
    public function period(): string
    {
        return array_key_exists($this->month, $this->periods()) ? $this->month : Period::current();
    }

    /**
     * The current month and the twelve before it, newest first: period => "October 2026".
     *
     * @return array<string, string>
     */
    public function periods(): array
    {
        $periods = [];
        $period = Period::current();

        foreach (range(0, self::MONTHS_BACK) as $ignored) {
            $periods[$period] = Period::firstDay($period)->format('F Y');
            $period = Period::previous($period);
        }

        return $periods;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportSessions()),
        ];
    }

    /**
     * The sessions table as a CSV download, for the chosen month.
     */
    private function exportSessions(): StreamedResponse
    {
        $period = $this->period();
        $sessions = (new MonthReport($period))->sessions();

        return response()->streamDownload(function () use ($sessions): void {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['Date', 'Title', 'Capacity', 'Occupied', 'Fill %', 'Attended'], escape: '');

            foreach ($sessions as $session) {
                fputcsv($file, [
                    $session['startsAt']->format('Y-m-d H:i'),
                    $session['title'],
                    $session['capacity'],
                    $session['occupied'],
                    $session['fillPercent'],
                    $session['attended'],
                ], escape: '');
            }

            fclose($file);
        }, "optifit-sessions-{$period}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function getViewData(): array
    {
        $report = new MonthReport($this->period());

        return [
            'totals' => $report->totals(),
            'credits' => $report->credits(),
            'sessions' => $report->sessions(),
            'memberships' => $report->memberships(),
            'membershipCount' => $report->activeMemberships(),
            'income' => Money::format($report->monthlyIncome()),
            'isCurrent' => $this->period() === Period::current(),
        ];
    }
}
