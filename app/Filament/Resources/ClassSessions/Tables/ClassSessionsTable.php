<?php

namespace App\Filament\Resources\ClassSessions\Tables;

use App\Enums\SessionStatus;
use App\Filament\Resources\ClassSessions\Actions\SessionActions;
use App\Models\ClassSession;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClassSessionsTable
{
    /** docs/05: the date filter starts as today to this many days ahead. */
    public const DEFAULT_RANGE_DAYS = 14;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(self::withOccupancy(...))
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('When')
                    ->dateTime('D j M, H:i')
                    ->sortable()
                    ->weight('semibold')
                    // On a phone the time and the title identify the row.
                    ->description(fn (ClassSession $record) => $record->title),
                TextColumn::make('places')
                    ->label('Booked')
                    ->state(fn (ClassSession $record) => ($record->occupying_bookings_count).'/'.$record->capacity)
                    ->description(fn (ClassSession $record) => (int) $record->guest_places > 0 ? '+'.$record->guest_places.' '.str('guest')->plural((int) $record->guest_places) : null)
                    ->color(fn (ClassSession $record) => $record->isScheduled() && $record->isBelowMinimum() ? 'danger' : null),
                TextColumn::make('location.name')->label('Venue')->visibleFrom('lg'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ClassSession $record) => $record->status->label())
                    ->color(fn (ClassSession $record) => $record->status->filamentColor())
                    ->visibleFrom('md'),
            ])
            ->filters([
                self::dateFilter(),
                SelectFilter::make('status')->options(collect(SessionStatus::cases())->mapWithKeys(fn (SessionStatus $status) => [$status->value => $status->label()])->all()),
                SelectFilter::make('location_id')->label('Venue')->relationship('location', 'name'),
            ])
            // One menu per row: separate buttons run off the edge of a phone screen (docs/05).
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('Open roster')->icon(Heroicon::OutlinedClipboardDocumentList),
                    EditAction::make(),
                    SessionActions::duplicate(),
                    SessionActions::cancelSession(),
                ]),
            ]);
    }

    /**
     * @param  Builder<ClassSession>  $query
     * @return Builder<ClassSession>
     */
    private static function withOccupancy(Builder $query): Builder
    {
        return $query->with('location')->withOccupancy();
    }

    /**
     * @return array{from: string|null, until: string|null}
     */
    public static function defaultDateRange(): array
    {
        return ['from' => today()->toDateString(), 'until' => today()->addDays(self::DEFAULT_RANGE_DAYS)->toDateString()];
    }

    private static function dateFilter(): Filter
    {
        return Filter::make('date')
            ->label('Dates')
            ->schema([
                DatePicker::make('from')->default(fn () => self::defaultDateRange()['from']),
                DatePicker::make('until')->default(fn () => self::defaultDateRange()['until']),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data) => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('starts_at', '>=', $from))
                ->when($data['until'] ?? null, fn (Builder $query, string $until) => $query->whereDate('starts_at', '<=', $until)))
            ->indicateUsing(function (array $data): array {
                $indicators = [];

                if ($data['from'] ?? null) {
                    $indicators[] = Indicator::make('From '.date('j M', strtotime($data['from'])))->removeField('from');
                }

                if ($data['until'] ?? null) {
                    $indicators[] = Indicator::make('Until '.date('j M', strtotime($data['until'])))->removeField('until');
                }

                return $indicators;
            });
    }
}
