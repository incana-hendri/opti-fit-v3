<?php

namespace App\Filament\Resources\ClassSessions\Schemas;

use App\Models\ClassSession;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The top of the roster (docs/05 § Sessions): date and time, places left, cutoff and the minimum indicator.
 */
class ClassSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(['default' => 2, 'md' => 3])
                    ->schema([
                        TextEntry::make('starts_at')
                            ->label('When')
                            ->state(fn (ClassSession $record) => $record->starts_at->format('D j M, H:i').' · '.$record->duration_minutes.' min'),
                        TextEntry::make('location.name')->label('Venue'),
                        TextEntry::make('places_left')
                            ->label('Places left')
                            ->state(fn (ClassSession $record) => $record->placesLeft().' of '.$record->capacity)
                            ->badge()
                            ->color(fn (ClassSession $record) => $record->placesLeft() > 3 ? 'success' : ($record->placesLeft() > 0 ? 'warning' : 'gray')),
                        TextEntry::make('cutoff')
                            ->label('Booking closes')
                            ->state(fn (ClassSession $record) => $record->cutoffAt()->format('D j M, H:i')),
                        TextEntry::make('minimum')
                            ->label('Minimum')
                            ->state(fn (ClassSession $record) => match (true) {
                                $record->min_participants === null => 'No minimum',
                                $record->isBelowMinimum() => "Below minimum ({$record->occupied()} of {$record->min_participants})",
                                default => "Minimum met ({$record->occupied()} of {$record->min_participants})",
                            })
                            ->badge()
                            ->color(fn (ClassSession $record) => match (true) {
                                $record->min_participants === null => 'gray',
                                $record->isBelowMinimum() => 'danger',
                                default => 'success',
                            }),
                        TextEntry::make('status')
                            ->state(fn (ClassSession $record) => $record->status->label().($record->cancellation_reason ? ': '.$record->cancellation_reason : ''))
                            ->badge()
                            ->color(fn (ClassSession $record) => $record->status->filamentColor()),
                    ]),
            ]);
    }
}
