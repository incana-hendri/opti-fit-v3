<?php

namespace App\Filament\Resources\ClassSessions\RelationManagers;

use App\Filament\Concerns\RefreshesWithPage;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

// The Guests table on the roster (docs/05 § Sessions; BR-9.3): they take places and cost no credits.
class GuestsRelationManager extends RelationManager
{
    use RefreshesWithPage;

    protected static string $relationship = 'guestEntries';

    protected static ?string $title = 'Guests';

    // Relation managers on a view page are read-only by default; admins add and remove guests here.
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Name or label')->required()->maxLength(255),
                TextInput::make('participant_count')->label('People')->numeric()->integer()->default(1)->required()->minValue(1)->maxValue(100),
                TextInput::make('note')->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->weight('semibold')->description(fn ($record) => $record->note),
                TextColumn::make('participant_count')->label('People')->numeric(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add guest')
                    ->mutateDataUsing(function (array $data): array {
                        /** @var User $admin */
                        $admin = Auth::user();

                        return $data + ['created_by' => $admin->id];
                    }),
            ])
            ->recordActions([
                DeleteAction::make()->iconButton(),
            ]);
    }
}
