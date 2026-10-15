<?php

namespace App\Filament\Resources\Households\RelationManagers;

use App\Models\Membership;
use App\Support\Money;
use App\Support\Period;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

// The Memberships tab (docs/05 § Households): the household's full membership history, read-only.
// Approving, pausing and the rest happen on the Memberships page.
class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['package', 'members']))
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('package.name')
                    ->label('Package')
                    ->weight('semibold')
                    ->description(fn (Membership $record) => $record->members->pluck('first_name')->join(', ', ' and ')),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Membership $record) => $record->status->label())
                    ->color(fn (Membership $record) => $record->status->filamentColor()),
                TextColumn::make('monthly_credits')->label('Credits')->numeric()->visibleFrom('md'),
                TextColumn::make('monthly_price_cents')
                    ->label('Price')
                    ->formatStateUsing(fn (int $state) => Money::format($state))
                    ->visibleFrom('md'),
                TextColumn::make('starts_period')
                    ->label('From')
                    ->formatStateUsing(fn (?string $state) => $state ? Period::label($state) : 'Not started')
                    ->visibleFrom('md'),
                TextColumn::make('ends_period')
                    ->label('Until')
                    ->formatStateUsing(fn (?string $state) => $state ? Period::label($state) : '')
                    ->visibleFrom('lg'),
                TextColumn::make('requested_at')->label('Requested')->date('j M Y')->visibleFrom('lg'),
            ]);
    }
}
