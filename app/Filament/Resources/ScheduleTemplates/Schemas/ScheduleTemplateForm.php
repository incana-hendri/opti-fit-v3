<?php

namespace App\Filament\Resources\ScheduleTemplates\Schemas;

use App\Enums\Weekday;
use App\Models\Location;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ScheduleTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description('Editing a template never changes classes that were already generated (BR-8.3).')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')->required()->maxLength(255),
                        Select::make('location_id')
                            ->label('Venue')
                            ->options(fn () => Location::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->required(),
                        Select::make('weekday')->options(Weekday::options())->required(),
                        TimePicker::make('start_time')->label('Starts at')->seconds(false)->required(),
                        TextInput::make('duration_minutes')->label('Minutes')->numeric()->integer()->default(45)->required()->minValue(5)->maxValue(240),
                        TextInput::make('capacity')->numeric()->integer()->default(15)->required()->minValue(1)->maxValue(500),
                        TextInput::make('min_participants')
                            ->label('Minimum participants')
                            ->helperText('Admins only: you get an email at 06:00 if a class is below this.')
                            ->numeric()->integer()->minValue(1)->maxValue(500),
                        TextInput::make('cutoff_hours')
                            ->label('Cutoff hours')
                            ->helperText('Leave empty to use the studio default.')
                            ->numeric()->integer()->minValue(0)->maxValue(168),
                        Textarea::make('description')->label('Public description')->maxLength(255)->rows(2)->columnSpanFull(),
                        Toggle::make('is_active')->label('Active')->default(true)->helperText('Only active templates are used by default when generating sessions.'),
                    ]),
            ]);
    }
}
