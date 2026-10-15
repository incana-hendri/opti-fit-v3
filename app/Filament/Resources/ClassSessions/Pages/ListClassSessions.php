<?php

namespace App\Filament\Resources\ClassSessions\Pages;

use App\Filament\Resources\ClassSessions\ClassSessionResource;
use App\Filament\Resources\ClassSessions\Tables\ClassSessionsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListClassSessions extends ListRecords
{
    protected static string $resource = ClassSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Upcoming (earliest first, from today) and Past (latest first), as docs/05 asks.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('starts_at', '>=', today())->reorder('starts_at')),
            'past' => Tab::make('Past')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('starts_at', '<', today())->reorder('starts_at', 'desc')),
        ];
    }

    // The default date range would hide every past class, so the Past tab has none: here when switching, and in the filter defaults when opened by URL.
    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        $this->tableFilters['date'] = ClassSessionsTable::defaultDateRange($this->activeTab);
    }
}
