<?php

namespace App\Filament\Resources\Admins\Tables;

use App\Actions\Admins\DeactivateAdmin;
use App\Exceptions\AccountException;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AdminsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('first_name')
            ->columns([
                TextColumn::make('first_name')
                    ->label('Name')
                    ->weight('semibold')
                    ->state(fn (User $record) => $record->fullName())
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")))
                    ->sortable()
                    // On a phone the name identifies the row; the email appears under it.
                    ->description(fn (User $record) => $record->email),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (User $record) => $record->isDeactivated() ? 'Deactivated' : 'Active')
                    ->color(fn (string $state) => $state === 'Active' ? 'success' : 'gray'),
                TextColumn::make('created_at')->label('Created')->date('j M Y')->sortable()->visibleFrom('md'),
            ])
            // One menu per row: separate buttons run off the edge of a phone screen (docs/05).
            ->recordActions([
                ActionGroup::make([
                    Action::make('deactivate')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->visible(fn (User $record) => ! $record->isDeactivated())
                        ->requiresConfirmation()
                        ->modalDescription("They won't be able to open the admin panel any more.")
                        ->action(function (User $record) {
                            try {
                                app(DeactivateAdmin::class)->handle($record, self::admin());
                                Notification::make()->title('Admin deactivated')->success()->send();
                            } catch (AccountException $exception) {
                                Notification::make()->title($exception->getMessage())->danger()->send();
                            }
                        }),
                    Action::make('reactivate')
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->visible(fn (User $record) => $record->isDeactivated())
                        ->requiresConfirmation()
                        ->action(function (User $record) {
                            $record->forceFill(['deactivated_at' => null])->save();
                            Notification::make()->title('Admin reactivated')->success()->send();
                        }),
                ]),
            ]);
    }

    private static function admin(): User
    {
        /** @var User */
        return Auth::user();
    }
}
