<?php

namespace App\Filament\Resources\Memberships\Tables;

use App\Filament\Resources\Memberships\Actions\MembershipActions;
use App\Models\Membership;
use App\Support\Money;
use App\Support\Period;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MembershipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['household.user', 'package', 'members']))
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('household.name')
                    ->label('Household')
                    ->weight('semibold')
                    ->searchable()
                    // On a phone the household and package identify the row; the rest appears from wider screens.
                    ->description(fn (Membership $record) => $record->package->name),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Membership $record) => $record->status->label())
                    ->color(fn (Membership $record) => $record->status->filamentColor()),
                TextColumn::make('members')
                    ->label('Covers')
                    ->state(fn (Membership $record) => $record->members->pluck('first_name')->join(', ', ' and '))
                    ->visibleFrom('md'),
                TextColumn::make('monthly_credits')
                    ->label('Credits')
                    ->numeric()
                    ->visibleFrom('md'),
                TextColumn::make('monthly_price_cents')
                    ->label('Price')
                    ->formatStateUsing(fn (int $state) => Money::format($state))
                    ->visibleFrom('md'),
                TextColumn::make('period')
                    ->label('Period')
                    ->state(fn (Membership $record) => self::periods($record))
                    ->visibleFrom('lg'),
            ])
            // One menu per row: separate buttons run off the edge of a phone screen (docs/05: usable on a phone).
            ->recordActions([
                ActionGroup::make([
                    MembershipActions::approve(),
                    MembershipActions::decline(),
                    MembershipActions::edit(),
                    MembershipActions::pause(),
                    MembershipActions::resume(),
                    MembershipActions::cancelNow(),
                ]),
            ]);
    }

    private static function periods(Membership $membership): string
    {
        if ($membership->starts_period === null) {
            return 'Requested '.$membership->requested_at->format('j M');
        }

        $start = Period::label($membership->starts_period);

        return $membership->ends_period === null ? "From {$start}" : "{$start} to ".Period::label($membership->ends_period);
    }
}
