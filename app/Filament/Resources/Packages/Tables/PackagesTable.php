<?php

namespace App\Filament\Resources\Packages\Tables;

use App\Models\Package;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('prices')->withCount(['prices', 'memberships']))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->weight('semibold')
                    ->searchable()
                    // On a phone the name is the only identifying column.
                    ->description(fn (Package $record) => $record->credits_per_member.' credits per member'),
                TextColumn::make('price_for_one')
                    ->label('1 member')
                    ->state(fn (Package $record) => Money::format($record->priceFor(1)))
                    ->visibleFrom('md'),
                TextColumn::make('prices_count')
                    ->label('Tiers')
                    ->numeric()
                    ->visibleFrom('lg'),
                TextColumn::make('memberships_count')
                    ->label('Memberships')
                    ->numeric()
                    ->visibleFrom('md'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            // One menu per row: separate buttons run off the edge of a phone screen (docs/05: usable on a phone).
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    // BR-3.3: archiving keeps existing memberships and hides the package from new requests.
                    Action::make('archive')
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->color('gray')
                        ->visible(fn (Package $record) => $record->is_active)
                        ->requiresConfirmation()
                        ->modalDescription('Existing memberships continue. The package is hidden from the pricing page and new requests.')
                        ->action(fn (Package $record) => $record->update(['is_active' => false])),
                    Action::make('unarchive')
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->color('gray')
                        ->visible(fn (Package $record) => ! $record->is_active)
                        ->action(fn (Package $record) => $record->update(['is_active' => true])),
                    // BR-3.4: only a package that no membership ever used can be deleted.
                    DeleteAction::make()
                        ->visible(fn (Package $record) => $record->memberships_count === 0),
                ]),
            ]);
    }
}
