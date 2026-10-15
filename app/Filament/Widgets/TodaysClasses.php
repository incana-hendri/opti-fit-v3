<?php

namespace App\Filament\Widgets;

use App\Models\ClassSession;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

// docs/05 § Dashboard 1: today's and tomorrow's classes, each linking to its roster.
class TodaysClasses extends Widget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.todays-classes';

    /**
     * Scheduled classes only: a cancelled class has nothing to run. Grouped by day, today first.
     *
     * @return Collection<array-key, Collection<int, ClassSession>>
     */
    public function days(): Collection
    {
        return ClassSession::query()
            ->scheduled()
            ->whereBetween('starts_at', [today(), today()->addDay()->endOfDay()])
            ->withOccupancy()
            ->orderBy('starts_at')
            ->get()
            ->toBase()
            ->groupBy(fn (ClassSession $session) => $session->starts_at->isToday() ? 'Today' : 'Tomorrow');
    }

    protected function getViewData(): array
    {
        return ['days' => $this->days()];
    }
}
