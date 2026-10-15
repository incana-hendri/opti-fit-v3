<?php

namespace App\Filament\Resources\ScheduleTemplates\Tables;

use App\Models\ScheduleTemplate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ScheduleTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('location')->orderBy('weekday')->orderBy('start_time'))
            ->columns([
                TextColumn::make('weekday')
                    ->label('When')
                    ->weight('semibold')
                    ->formatStateUsing(fn (ScheduleTemplate $record) => $record->weekday->shortLabel().' '.substr($record->start_time, 0, 5))
                    // On a phone the slot and the title identify the row.
                    ->description(fn (ScheduleTemplate $record) => $record->title),
                TextColumn::make('location.name')->label('Venue')->visibleFrom('lg'),
                TextColumn::make('capacity')->numeric()->visibleFrom('md'),
                TextColumn::make('duration_minutes')->label('Minutes')->numeric()->visibleFrom('lg'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('deactivate')
                        ->icon(Heroicon::OutlinedPauseCircle)
                        ->color('gray')
                        ->visible(fn (ScheduleTemplate $record) => $record->is_active)
                        ->action(fn (ScheduleTemplate $record) => $record->update(['is_active' => false])),
                    Action::make('activate')
                        ->icon(Heroicon::OutlinedPlayCircle)
                        ->color('gray')
                        ->visible(fn (ScheduleTemplate $record) => ! $record->is_active)
                        ->action(fn (ScheduleTemplate $record) => $record->update(['is_active' => true])),
                ]),
            ]);
    }
}
