<?php

namespace App\Filament\Resources\Households\Pages;

use App\Filament\Resources\Households\HouseholdResource;
use App\Models\Household;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Password;

class ViewHousehold extends ViewRecord
{
    protected static string $resource = HouseholdResource::class;

    // docs/05: Overview, Members, Credits, Bookings and Memberships as tabs.
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Overview';
    }

    protected function getHeaderActions(): array
    {
        return [
            // BR-1.8
            Action::make('deactivate')
                ->label('Deactivate login')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription("They won't be able to log in. Their bookings stay as they are.")
                ->visible(fn (Household $record) => ! $record->user->isDeactivated())
                ->action(function (Household $record) {
                    $record->user->forceFill(['deactivated_at' => now()])->save();
                    Notification::make()->title('Login deactivated')->success()->send();
                }),
            Action::make('reactivate')
                ->label('Reactivate login')
                ->requiresConfirmation()
                ->visible(fn (Household $record) => $record->user->isDeactivated())
                ->action(function (Household $record) {
                    $record->user->forceFill(['deactivated_at' => null])->save();
                    Notification::make()->title('Login reactivated')->success()->send();
                }),
            Action::make('sendPasswordReset')
                ->label('Send password reset email')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(fn (Household $record) => "We'll email a reset link to {$record->user->email}.")
                ->action(function (Household $record) {
                    $status = Password::broker()->sendResetLink(['email' => $record->user->email]);

                    $status === Password::RESET_LINK_SENT
                        ? Notification::make()->title('Reset link sent')->success()->send()
                        : Notification::make()->title(__($status))->danger()->send();
                }),
        ];
    }
}
