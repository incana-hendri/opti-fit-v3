<?php

namespace App\Filament\Resources\ClassSessions\Schemas;

use App\Models\ClassSession;
use App\Models\Location;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClassSessionForm
{
    // BR-7.1
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')->required()->maxLength(255),
                        Select::make('location_id')
                            ->label('Venue')
                            // An archived venue stays selectable on a session that already uses it.
                            ->options(fn (?ClassSession $record) => Location::query()->where('is_active', true)->when($record, fn ($query) => $query->orWhere('id', $record->location_id))->orderBy('name')->pluck('name', 'id'))
                            ->required(),
                        DateTimePicker::make('starts_at')->label('Starts')->seconds(false)->required(),
                        TextInput::make('duration_minutes')->label('Minutes')->numeric()->integer()->default(45)->required()->minValue(5)->maxValue(240),
                        TextInput::make('capacity')
                            ->helperText('Changing it never touches existing bookings.')
                            ->numeric()->integer()->default(15)->required()->minValue(1)->maxValue(500),
                        TextInput::make('min_participants')
                            ->label('Minimum participants')
                            ->helperText('Admins only: you get an email at 06:00 if the class is below this.')
                            ->numeric()->integer()->minValue(1)->maxValue(500),
                        TextInput::make('cutoff_hours')
                            ->label('Cutoff hours')
                            ->helperText('Hours before the start that booking and free cancellation close. Leave empty to use the studio default.')
                            ->numeric()->integer()->minValue(0)->maxValue(168),
                        Textarea::make('description')->label('Public description')->maxLength(500)->rows(2)->columnSpanFull(),
                    ]),
            ]);
    }
}
