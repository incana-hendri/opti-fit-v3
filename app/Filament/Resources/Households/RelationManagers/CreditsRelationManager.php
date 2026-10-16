<?php

namespace App\Filament\Resources\Households\RelationManagers;

use App\Actions\Credits\AdjustCredits;
use App\Enums\CreditEntryType;
use App\Exceptions\CreditException;
use App\Filament\Concerns\RefreshesWithPage;
use App\Models\CreditEntry;
use App\Models\Household;
use App\Models\User;
use App\Support\CreditLedger;
use App\Support\Period;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

// The Credits tab (docs/05 § Households): the current balance, the whole ledger, and "Adjust credits" (BR-6.5).
class CreditsRelationManager extends RelationManager
{
    use RefreshesWithPage;

    protected static string $relationship = 'creditEntries';

    protected static ?string $title = 'Credits';

    // The ledger rows themselves stay read-only (BR-5.3); this only allows the "Adjust credits" header action.
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var Household $household */
        $household = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('creator'))
            ->heading(fn () => 'Balance for '.Period::label(Period::current()).': '.CreditLedger::balance($household))
            ->description('Every credit change is a new row. Entries are never edited or deleted.')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('D j M, H:i')
                    ->description(fn (CreditEntry $record) => Period::label($record->period)),
                TextColumn::make('description')
                    ->wrap()
                    ->description(fn (CreditEntry $record) => $record->creator?->fullName()),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (CreditEntry $record) => $record->type->label())
                    ->color(fn (CreditEntry $record) => match ($record->type) {
                        CreditEntryType::Grant, CreditEntryType::Refund => 'success',
                        CreditEntryType::Adjustment => 'info',
                        CreditEntryType::Booking => 'gray',
                        CreditEntryType::Expiry => 'warning',
                    })
                    ->visibleFrom('md'),
                TextColumn::make('amount')
                    ->label('Credits')
                    ->numeric()
                    ->formatStateUsing(fn (int $state) => $state > 0 ? "+{$state}" : (string) $state)
                    ->weight('semibold')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->options(fn () => $household->creditEntries()->distinct()->orderByDesc('period')->pluck('period', 'period')
                        ->map(fn (string $period) => Period::label($period).' ('.$period.')')->all()),
            ])
            ->headerActions([
                Action::make('adjust')
                    ->label('Adjust credits')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->modalHeading('Adjust credits')
                    ->modalDescription('Adds or removes credits in '.Period::label(Period::current()).'. A removal can never take the balance below zero.')
                    ->modalSubmitActionLabel('Save adjustment')
                    ->schema([
                        TextInput::make('amount')
                            ->helperText('Use a negative number to remove credits.')
                            ->numeric()->integer()->required()->minValue(-1000)->maxValue(1000)->notIn([0]),
                        Textarea::make('reason')->required()->maxLength(200)->rows(2),
                    ])
                    ->action(function (array $data) use ($household) {
                        try {
                            app(AdjustCredits::class)->handle($household, (int) $data['amount'], $data['reason'], $this->admin());
                            Notification::make()->title('Credits adjusted')->success()->send();
                        } catch (CreditException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    private function admin(): User
    {
        /** @var User */
        return Auth::user();
    }
}
