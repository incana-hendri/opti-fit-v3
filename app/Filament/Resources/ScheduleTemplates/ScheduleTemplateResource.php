<?php

namespace App\Filament\Resources\ScheduleTemplates;

use App\Filament\Resources\ScheduleTemplates\Pages\CreateScheduleTemplate;
use App\Filament\Resources\ScheduleTemplates\Pages\EditScheduleTemplate;
use App\Filament\Resources\ScheduleTemplates\Pages\ListScheduleTemplates;
use App\Filament\Resources\ScheduleTemplates\Schemas\ScheduleTemplateForm;
use App\Filament\Resources\ScheduleTemplates\Tables\ScheduleTemplatesTable;
use App\Models\ScheduleTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The weekly timetable that "Generate sessions" turns into classes (docs/05 § Timetable templates; BR-8.x).
 */
class ScheduleTemplateResource extends Resource
{
    protected static ?string $model = ScheduleTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Classes';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'template';

    protected static ?string $pluralModelLabel = 'Timetable templates';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ScheduleTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScheduleTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScheduleTemplates::route('/'),
            'create' => CreateScheduleTemplate::route('/create'),
            'edit' => EditScheduleTemplate::route('/{record}/edit'),
        ];
    }
}
