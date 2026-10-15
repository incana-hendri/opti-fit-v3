<?php

namespace App\Filament\Resources\ClassSessions\RelationManagers;

use App\Enums\BookingStatus;
use App\Filament\Resources\ClassSessions\Actions\BookingActions;
use App\Models\Booking;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// The roster's Bookings table (docs/05 § Sessions): who is coming, health notes, attendance and credits.
class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Bookings';

    // Relation managers on a view page are read-only by default; the roster marks attendance and cancels here.
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(self::withDetails(...))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('member.first_name')
                    ->label('Member')
                    ->weight('semibold')
                    ->formatStateUsing(fn (Booking $record) => $record->member->fullName())
                    // On a phone the member and their household identify the row.
                    ->description(fn (Booking $record) => $record->household->name),
                TextColumn::make('age')
                    ->state(fn (Booking $record) => $record->member->age())
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Booking $record) => $record->status->label())
                    ->color(fn (Booking $record) => $record->status->filamentColor()),
                IconColumn::make('credit_charged')
                    ->label('Credit')
                    ->boolean()
                    ->tooltip(fn (Booking $record) => match (true) {
                        ! $record->credit_charged => 'Free booking',
                        $record->isRefunded() => 'Charged, then returned',
                        default => 'Charged',
                    })
                    ->visibleFrom('md'),
                IconColumn::make('health_notes')
                    ->label('Health')
                    ->state(fn (Booking $record) => filled($record->member->health_notes))
                    ->icon(fn (bool $state) => $state ? Heroicon::OutlinedExclamationTriangle : null)
                    ->color('warning')
                    ->tooltip(fn (Booking $record) => $record->member->health_notes),
            ])
            ->filters([
                Filter::make('hideCancelled')
                    ->label('Hide cancelled')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query) => $query->where('status', '!=', BookingStatus::Cancelled)),
            ])
            ->recordActions([
                ActionGroup::make([
                    BookingActions::markAttended(),
                    BookingActions::markNoShow(),
                    BookingActions::cancel(),
                    BookingActions::returnCredit(),
                ]),
            ])
            ->toolbarActions([
                BookingActions::markSelectedAttended(),
            ]);
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    private static function withDetails(Builder $query): Builder
    {
        return $query->with(['member', 'household', 'classSession'])->withRefunded();
    }
}
