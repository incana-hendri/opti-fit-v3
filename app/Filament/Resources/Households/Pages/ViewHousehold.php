<?php

namespace App\Filament\Resources\Households\Pages;

use App\Actions\Households\AnonymiseHousehold;
use App\Exceptions\AccountException;
use App\Filament\Resources\Households\HouseholdResource;
use App\Models\Household;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
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
                ->visible(fn (Household $record) => ! $record->isAnonymised() && ! $record->user->isDeactivated())
                ->action(function (Household $record) {
                    $record->user->forceFill(['deactivated_at' => now()])->save();
                    Notification::make()->title('Login deactivated')->success()->send();
                }),
            Action::make('reactivate')
                ->label('Reactivate login')
                ->requiresConfirmation()
                ->visible(fn (Household $record) => ! $record->isAnonymised() && $record->user->isDeactivated())
                ->action(function (Household $record) {
                    $record->user->forceFill(['deactivated_at' => null])->save();
                    Notification::make()->title('Login reactivated')->success()->send();
                }),
            Action::make('sendPasswordReset')
                ->label('Send password reset email')
                ->color('gray')
                ->visible(fn (Household $record) => ! $record->isAnonymised())
                ->requiresConfirmation()
                ->modalDescription(fn (Household $record) => "We'll email a reset link to {$record->user->email}.")
                ->action(function (Household $record) {
                    $status = Password::broker()->sendResetLink(['email' => $record->user->email]);

                    $status === Password::RESET_LINK_SENT
                        ? Notification::make()->title('Reset link sent')->success()->send()
                        : Notification::make()->title(__($status))->danger()->send();
                }),
            $this->anonymise(),
        ];
    }

    // BR-9.6: POPIA erasure, behind a typed confirmation.
    private function anonymise(): Action
    {
        return Action::make('anonymise')
            ->label('Anonymise household')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (Household $record) => ! $record->isAnonymised())
            ->modalHeading('Anonymise this household?')
            ->modalDescription('This cannot be undone. The names, email, phone, birth dates, health notes and emergency contact are replaced, '
                .'the login stops working, the membership ends now, and future bookings are cancelled without refunds. '
                .'The credit history and past bookings stay for the reports.')
            ->modalSubmitActionLabel('Anonymise')
            ->schema([
                TextInput::make('confirmation')
                    ->label('Type ANONYMISE to confirm')
                    ->required()
                    ->rules(['in:ANONYMISE'])
                    ->validationMessages(['in' => 'Type ANONYMISE, in capitals, to confirm.']),
            ])
            ->action(function (Household $record) {
                try {
                    /** @var User $admin */
                    $admin = Auth::user();
                    app(AnonymiseHousehold::class)->handle($record, $admin);
                    Notification::make()->title('Household anonymised')->success()->send();
                } catch (AccountException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }
}
