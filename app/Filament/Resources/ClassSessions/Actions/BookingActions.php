<?php

namespace App\Filament\Resources\ClassSessions\Actions;

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\MarkAttendance;
use App\Actions\Credits\ReturnCredit;
use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Exceptions\CreditException;
use App\Models\Booking;
use App\Models\User;
use App\Support\Cutoff;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The booking row actions used on the roster and on a household's Bookings tab (docs/05).
 * Each one only collects input and calls the matching action class.
 */
class BookingActions
{
    // BR-7.9
    public static function markAttended(): Action
    {
        return Action::make('markAttended')
            ->label('Mark attended')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Booking $record) => in_array($record->status, [BookingStatus::Booked, BookingStatus::NoShow], true))
            ->action(fn (Booking $record) => self::attempt(fn () => app(MarkAttendance::class)->handle($record, BookingStatus::Attended), 'Marked attended'));
    }

    // BR-7.9
    public static function markNoShow(): Action
    {
        return Action::make('markNoShow')
            ->label('Mark no-show')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Booking $record) => in_array($record->status, [BookingStatus::Booked, BookingStatus::Attended], true))
            ->action(fn (Booking $record) => self::attempt(fn () => app(MarkAttendance::class)->handle($record, BookingStatus::NoShow), 'Marked no-show'));
    }

    // BR-6.2: "return credit" defaults to on before the cutoff and off after it.
    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel booking')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('gray')
            ->modalHeading(fn (Booking $record) => "Cancel {$record->member->first_name}'s booking?")
            ->visible(fn (Booking $record) => $record->status === BookingStatus::Booked)
            ->fillForm(fn (Booking $record) => ['return_credit' => $record->credit_charged && ! Cutoff::hasPassed($record->classSession)])
            ->schema(fn (Booking $record) => [
                Toggle::make('return_credit')
                    ->label('Return the credit')
                    ->disabled(! $record->credit_charged)
                    ->helperText($record->credit_charged
                        ? 'On before the cutoff and off after it, unless you change it.'
                        : 'This was a free booking, so there is no credit to return.'),
            ])
            ->action(fn (Booking $record, array $data) => self::attempt(
                fn () => app(CancelBooking::class)->handle($record, self::admin(), (bool) ($data['return_credit'] ?? false)),
                'Booking cancelled',
            ));
    }

    // BR-6.1, 5.7
    public static function returnCredit(): Action
    {
        return Action::make('returnCredit')
            ->label('Return credit')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Adds 1 credit back to the household for this month. This can only be done once per booking.')
            ->visible(fn (Booking $record) => $record->canReturnCredit())
            ->action(fn (Booking $record) => self::attempt(fn () => app(ReturnCredit::class)->handle($record, self::admin()), 'Credit returned'));
    }

    // BR-7.9: for a roster, mark everyone who turned up in one go.
    public static function markSelectedAttended(): BulkAction
    {
        return BulkAction::make('markSelectedAttended')
            ->label('Mark selected attended')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records) => self::markAttendedInBulk($records));
    }

    /**
     * @param  Collection<int, Booking>  $records
     */
    private static function markAttendedInBulk(Collection $records): void
    {
        $marked = 0;

        foreach ($records as $booking) {
            if (! in_array($booking->status, [BookingStatus::Booked, BookingStatus::NoShow], true)) {
                continue;
            }

            try {
                app(MarkAttendance::class)->handle($booking, BookingStatus::Attended);
                $marked++;
            } catch (BookingException $exception) {
                Notification::make()->title($exception->getMessage())->danger()->send();

                return;
            }
        }

        Notification::make()->title("Marked {$marked} attended")->success()->send();
    }

    private static function attempt(Closure $callback, string $success): void
    {
        try {
            $callback();
            Notification::make()->title($success)->success()->send();
        } catch (BookingException|CreditException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    private static function admin(): User
    {
        /** @var User */
        return Auth::user();
    }
}
