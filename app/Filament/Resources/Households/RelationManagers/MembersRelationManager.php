<?php

namespace App\Filament\Resources\Households\RelationManagers;

use App\Enums\Gender;
use App\Filament\Concerns\RefreshesWithPage;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

// The Members tab (docs/05 § Households): admins edit members, including health notes, and archive them.
class MembersRelationManager extends RelationManager
{
    use RefreshesWithPage;

    protected static string $relationship = 'members';

    // Relation managers on a view page are read-only by default; admins do edit members here.
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')->required()->maxLength(255),
                TextInput::make('last_name')->required()->maxLength(255),
                DatePicker::make('date_of_birth')->required()->maxDate(today()),
                Select::make('gender')->options(Gender::options())->required(),
                Textarea::make('health_notes')
                    ->helperText('Injuries or conditions the trainer should know about. Stored encrypted.')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('first_name')
            ->defaultSort('first_name')
            ->columns([
                TextColumn::make('first_name')
                    ->label('Name')
                    ->formatStateUsing(fn (Member $record) => $record->fullName())
                    ->description(fn (Member $record) => $record->is_account_holder ? 'Account holder' : null),
                TextColumn::make('date_of_birth')
                    ->label('Age')
                    ->formatStateUsing(fn (Member $record) => $record->age()),
                TextColumn::make('gender')
                    ->formatStateUsing(fn (Member $record) => $record->gender->label()),
                IconColumn::make('health_notes')
                    ->label('Health notes')
                    ->icon(fn (?string $state) => filled($state) ? Heroicon::OutlinedExclamationTriangle : null)
                    ->color('warning')
                    ->tooltip(fn (Member $record) => $record->health_notes),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Member $record) => $record->isArchived() ? 'Archived' : 'Active')
                    ->color(fn (string $state) => $state === 'Archived' ? 'gray' : 'success'),
            ])
            ->recordActions([
                EditAction::make(),
                // BR-2.5
                Action::make('archive')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription("Archived members can't be booked and don't count as covered.")
                    ->visible(fn (Member $record) => ! $record->isArchived())
                    ->action(function (Member $record) {
                        $record->forceFill(['archived_at' => now()])->save();
                        Notification::make()->title("{$record->first_name} archived")->success()->send();
                    }),
            ]);
    }
}
