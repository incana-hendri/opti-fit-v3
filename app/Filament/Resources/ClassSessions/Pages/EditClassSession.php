<?php

namespace App\Filament\Resources\ClassSessions\Pages;

use App\Actions\Sessions\RescheduleSession;
use App\Enums\BookingStatus;
use App\Exceptions\SessionException;
use App\Filament\Resources\ClassSessions\ClassSessionResource;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class EditClassSession extends EditRecord
{
    protected static string $resource = ClassSessionResource::class;

    public bool $timeChangeConfirmed = false;

    // BR-7.10: changing the time emails every booked household, so ask first.
    protected function beforeSave(): void
    {
        if ($this->timeChangeConfirmed || ! $this->startsAtChanged()) {
            return;
        }

        $this->mountAction('confirmTimeChange');
        $this->halt();
    }

    public function confirmTimeChangeAction(): Action
    {
        return Action::make('confirmTimeChange')
            ->modalHeading('Change the class time?')
            ->modalDescription(function () {
                $households = $this->householdsToEmail();

                return $households === 0
                    ? 'Nobody is booked, so nobody needs to be emailed.'
                    : "{$households} ".str('household')->plural($households).' will be emailed.';
            })
            ->modalSubmitActionLabel('Change time')
            ->requiresConfirmation()
            ->action(function () {
                $this->timeChangeConfirmed = true;
                $this->save();
            });
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $admin */
        $admin = Auth::user();
        /** @var ClassSession $record */
        try {
            app(RescheduleSession::class)->handle($record, $data, $admin);
        } catch (SessionException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
            $this->halt();
        }

        return $record->refresh();
    }

    private function startsAtChanged(): bool
    {
        /** @var ClassSession $record */
        $record = $this->getRecord();

        return ! Carbon::parse($this->data['starts_at'])->equalTo($record->starts_at);
    }

    private function householdsToEmail(): int
    {
        return Booking::query()->where('class_session_id', $this->getRecord()->getKey())
            ->where('status', BookingStatus::Booked)->distinct()->count('household_id');
    }
}
