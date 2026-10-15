<?php

namespace App\Filament\Resources\ClassSessions;

use App\Filament\Resources\ClassSessions\Pages\CreateClassSession;
use App\Filament\Resources\ClassSessions\Pages\EditClassSession;
use App\Filament\Resources\ClassSessions\Pages\ListClassSessions;
use App\Filament\Resources\ClassSessions\Pages\ViewClassSession;
use App\Filament\Resources\ClassSessions\RelationManagers\BookingsRelationManager;
use App\Filament\Resources\ClassSessions\RelationManagers\GuestsRelationManager;
use App\Filament\Resources\ClassSessions\Schemas\ClassSessionForm;
use App\Filament\Resources\ClassSessions\Schemas\ClassSessionInfolist;
use App\Filament\Resources\ClassSessions\Tables\ClassSessionsTable;
use App\Models\ClassSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Classes on the timetable and their roster (docs/05 § Sessions; BR-7.x).
 */
class ClassSessionResource extends Resource
{
    protected static ?string $model = ClassSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Classes';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'session';

    protected static ?string $pluralModelLabel = 'Sessions';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ClassSessionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClassSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BookingsRelationManager::class,
            GuestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassSessions::route('/'),
            'create' => CreateClassSession::route('/create'),
            'view' => ViewClassSession::route('/{record}'),
            'edit' => EditClassSession::route('/{record}/edit'),
        ];
    }
}
