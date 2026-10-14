<?php

namespace App\Filament\Resources\Households\Tables;

use App\Models\Household;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HouseholdsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->withCount('activeMembers'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Household')
                    ->weight('semibold')
                    ->sortable()
                    // docs/05: search by any name or email.
                    ->searchable(query: fn (Builder $query, string $search) => self::search($query, $search))
                    // On a phone this is the only identifying column (docs/05: usable on a phone).
                    ->description(fn (Household $record) => $record->user->fullName()),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->copyable()
                    ->visibleFrom('md'),
                TextColumn::make('user.phone')
                    ->label('Phone')
                    ->visibleFrom('lg'),
                TextColumn::make('active_members_count')
                    ->label('Members')
                    ->numeric()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('flags')
                    ->label('Flags')
                    ->badge()
                    ->state(fn (Household $record): array => array_values(array_filter([
                        $record->user->deletion_requested_at ? 'Deletion requested' : null,
                        $record->user->isDeactivated() ? 'Deactivated' : null,
                    ])))
                    ->color(fn (string $state) => $state === 'Deactivated' ? 'gray' : 'danger'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    /**
     * @param  Builder<Household>  $query
     */
    private static function search(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';

        $query->where(fn (Builder $query) => $query
            ->where('name', 'like', $like)
            ->orWhereHas('user', fn (Builder $user) => $user
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like))
            ->orWhereHas('members', fn (Builder $member) => $member
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)));
    }
}
