<?php

namespace App\Filament\Resources\Locations\Tables;

use App\Exceptions\SessionException;
use App\Models\Location;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->weight('semibold')
                    ->searchable()
                    // On a phone the name identifies the row; the address appears under it.
                    ->description(fn (Location $record) => $record->address),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            // One menu per row: separate buttons run off the edge of a phone screen (docs/05).
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    // BR-9.2
                    Action::make('archive')
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->color('gray')
                        ->visible(fn (Location $record) => $record->is_active)
                        ->requiresConfirmation()
                        ->modalDescription('The venue is hidden from new classes and templates. Past classes keep it.')
                        ->action(function (Location $record) {
                            try {
                                $record->archive();
                                Notification::make()->title("{$record->name} archived")->success()->send();
                            } catch (SessionException $exception) {
                                Notification::make()->title($exception->getMessage())->danger()->send();
                            }
                        }),
                    Action::make('unarchive')
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->color('gray')
                        ->visible(fn (Location $record) => ! $record->is_active)
                        ->action(fn (Location $record) => $record->update(['is_active' => true])),
                ]),
            ]);
    }
}
