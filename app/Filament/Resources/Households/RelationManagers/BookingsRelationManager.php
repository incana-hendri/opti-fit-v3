<?php

namespace App\Filament\Resources\Households\RelationManagers;

use App\Filament\Concerns\RefreshesWithPage;
use App\Filament\Resources\ClassSessions\Actions\BookingActions;
use App\Models\Booking;
use App\Models\ClassSession;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// The Bookings tab (docs/05 § Households): upcoming and past bookings across the members, with the roster's row actions.
class BookingsRelationManager extends RelationManager
{
    use RefreshesWithPage;

    protected static string $relationship = 'bookings';

    protected static ?string $title = 'Bookings';

    // The row actions (cancel, attendance, return credit) change bookings, so the tab is not read-only.
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereHas('classSession', fn (Builder $session) => $session->where('starts_at', '>=', now()))
                    ->orderBy(self::startsAt())),
            'past' => Tab::make('Past')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereHas('classSession', fn (Builder $session) => $session->where('starts_at', '<', now()))
                    ->orderByDesc(self::startsAt())),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(self::withDetails(...))
            ->columns([
                TextColumn::make('classSession.starts_at')
                    ->label('Class')
                    ->dateTime('D j M, H:i')
                    ->weight('semibold')
                    // On a phone the date and who it is for identify the row.
                    ->description(fn (Booking $record) => $record->member->first_name.' · '.$record->classSession->title),
                TextColumn::make('member.first_name')
                    ->label('Member')
                    ->formatStateUsing(fn (Booking $record) => $record->member->fullName())
                    ->visibleFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Booking $record) => $record->status->label())
                    ->color(fn (Booking $record) => $record->status->filamentColor()),
                IconColumn::make('credit_charged')->label('Credit')->boolean()->visibleFrom('md'),
            ])
            ->recordActions([
                ActionGroup::make([
                    BookingActions::markAttended(),
                    BookingActions::markNoShow(),
                    BookingActions::cancel(),
                    BookingActions::returnCredit(),
                ]),
            ]);
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    private static function withDetails(Builder $query): Builder
    {
        return $query->with(['member', 'classSession'])->withRefunded();
    }

    // Orders by the class's start without joining, so the relation keeps selecting only booking columns.
    private static function startsAt(): Builder
    {
        return ClassSession::query()->select('starts_at')->whereColumn('class_sessions.id', 'bookings.class_session_id');
    }
}
