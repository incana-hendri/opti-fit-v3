<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Models\Location;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('address')->required()->maxLength(255),
                        TextInput::make('map_url')->label('Map link')->url()->maxLength(255)->helperText('Optional. A Google Maps link customers can open.'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Switch it off to archive the venue. A venue with an active template or an upcoming class can\'t be archived.')
                            ->rules([fn (?Location $record): Closure => self::mustBeArchivable($record)]),
                    ]),
            ]);
    }

    // BR-9.2
    private static function mustBeArchivable(?Location $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            if ($record !== null && $record->is_active && ! $value && ! $record->isArchivable()) {
                $fail("{$record->name} is still used by an active template or an upcoming class, so it can't be archived.");
            }
        };
    }
}
