<?php

namespace App\Filament\Resources\Memberships;

use App\Enums\MembershipStatus;
use App\Filament\Resources\Memberships\Pages\ListMemberships;
use App\Filament\Resources\Memberships\Tables\MembershipsTable;
use App\Models\Membership;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Membership requests and memberships (docs/05 § Membership requests and memberships; BR-4.x).
 * Customers create them, so there are no create or edit pages: the row actions call the membership actions.
 */
class MembershipResource extends Resource
{
    protected static ?string $model = Membership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Members';

    protected static ?int $navigationSort = 2;

    protected static ?string $pluralModelLabel = 'Memberships';

    public static function table(Table $table): Table
    {
        return MembershipsTable::configure($table);
    }

    // Admins see how many requests are waiting without opening the page.
    public static function getNavigationBadge(): ?string
    {
        $waiting = Membership::query()->where('status', MembershipStatus::Requested)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberships::route('/'),
        ];
    }
}
