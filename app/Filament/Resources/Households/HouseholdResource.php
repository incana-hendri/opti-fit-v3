<?php

namespace App\Filament\Resources\Households;

use App\Filament\Resources\Households\Pages\ListHouseholds;
use App\Filament\Resources\Households\Pages\ViewHousehold;
use App\Filament\Resources\Households\RelationManagers\CreditsRelationManager;
use App\Filament\Resources\Households\RelationManagers\MembershipsRelationManager;
use App\Filament\Resources\Households\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Households\Schemas\HouseholdInfolist;
use App\Filament\Resources\Households\Tables\HouseholdsTable;
use App\Models\Household;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Admin view of customer households (docs/05 § Households). Customers create and edit their own
 * household, so there are no create or edit pages here.
 */
class HouseholdResource extends Resource
{
    protected static ?string $model = Household::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Members';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return HouseholdInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HouseholdsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
            CreditsRelationManager::class,
            MembershipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHouseholds::route('/'),
            'view' => ViewHousehold::route('/{record}'),
        ];
    }
}
