<?php

namespace App\Filament\Resources\ClassSessions\Actions;

use App\Actions\Sessions\CancelSession;
use App\Enums\BookingStatus;
use App\Exceptions\SessionException;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Row actions for a session, shared by the sessions table and the roster header (docs/05 § Sessions).
 */
class SessionActions
{
    // docs/05: copies every field except the bookings, onto another date at the same time.
    public static function duplicate(): Action
    {
        return Action::make('duplicate')
            ->label('Duplicate to date')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->modalHeading('Duplicate to another date')
            ->modalDescription('Copies the class at the same time. Bookings and guests are not copied.')
            ->modalSubmitActionLabel('Duplicate')
            ->schema([DatePicker::make('date')->required()])
            ->action(function (ClassSession $record, array $data) {
                $date = Carbon::parse($data['date']);
                $copy = ClassSession::query()->create(
                    $record->only(['title', 'location_id', 'duration_minutes', 'capacity', 'min_participants', 'cutoff_hours', 'description'])
                    + ['starts_at' => $date->setTimeFrom($record->starts_at)],
                );

                Notification::make()->title('Session created for '.$copy->starts_at->format('D j M, H:i'))->success()->send();
            });
    }

    // BR-7.8
    public static function cancelSession(): Action
    {
        return Action::make('cancelSession')
            ->label('Cancel session')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->modalHeading(fn (ClassSession $record) => "Cancel {$record->title}, {$record->starts_at->format('D j M H:i')}?")
            ->modalDescription(function (ClassSession $record) {
                $bookings = Booking::query()->where('class_session_id', $record->id)->where('status', BookingStatus::Booked);
                $households = (clone $bookings)->distinct()->count('household_id');

                return $households === 0
                    ? 'Nobody is booked, so nobody needs to be emailed.'
                    : "{$bookings->count()} bookings will be cancelled and their credits returned. {$households} ".str('household')->plural($households).' will be emailed.';
            })
            ->modalSubmitActionLabel('Cancel session')
            ->visible(fn (ClassSession $record) => $record->isScheduled())
            ->schema([Textarea::make('reason')->maxLength(255)->rows(2)->helperText('Optional. The customers see this in the email.')])
            ->action(function (ClassSession $record, array $data) {
                try {
                    app(CancelSession::class)->handle($record, $data['reason'] ?? null, self::admin());
                    Notification::make()->title('Session cancelled')->success()->send();
                } catch (SessionException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    private static function admin(): User
    {
        /** @var User */
        return Auth::user();
    }
}
