<?php

namespace App\Filament\Resources\ClassSessions\Pages;

use App\Actions\Bookings\AdminBook;
use App\Exceptions\BookingException;
use App\Filament\Resources\ClassSessions\ClassSessionResource;
use App\Models\ClassSession;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use App\Support\CreditLedger;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * The roster, used at the venue on a phone (docs/05 § Sessions).
 */
class ViewClassSession extends ViewRecord
{
    protected static string $resource = ClassSessionResource::class;

    public function getTitle(): string
    {
        /** @var ClassSession $session */
        $session = $this->getRecord();

        return $session->title.', '.$session->starts_at->format('D j M, H:i');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->addBooking(),
            EditAction::make()->color('gray'),
        ];
    }

    // BR-6.3, 6.4: pick a household, then its members, whether to charge, and whether to go over capacity.
    private function addBooking(): Action
    {
        return Action::make('addBooking')
            ->label('Add booking')
            ->icon(Heroicon::OutlinedUserPlus)
            ->modalHeading('Add booking')
            ->modalSubmitActionLabel('Book')
            ->visible(fn (ClassSession $record) => $record->isScheduled())
            ->fillForm(['charge' => true, 'over_capacity' => false])
            ->schema([
                Select::make('household_id')
                    ->label('Household')
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('member_ids', []))
                    ->getSearchResultsUsing(fn (string $search) => Household::query()
                        ->where('name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                        ->orWhereHas('members', fn (Builder $member) => $member->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                        ->orderBy('name')->limit(25)->pluck('name', 'id')->all())
                    ->getOptionLabelUsing(fn ($value) => Household::query()->find($value)?->name),
                CheckboxList::make('member_ids')
                    ->label('Who is coming?')
                    ->required()
                    ->live()
                    ->options(fn (Get $get) => $get('household_id')
                        ? Household::query()->find($get('household_id'))?->activeMembers()->oldest()->get()->mapWithKeys(fn (Member $member) => [$member->id => $member->fullName()])->all() ?? []
                        : [])
                    ->helperText('Any active member of the household, not only the ones their package covers.'),
                Toggle::make('charge')
                    ->label('Charge 1 credit each')
                    ->live()
                    ->helperText(fn (Get $get) => $get('household_id')
                        ? 'Their balance is '.CreditLedger::balance(Household::query()->findOrFail($get('household_id'))).' this month. Off makes it a free booking.'
                        : 'Off makes it a free booking.'),
                Checkbox::make('over_capacity')
                    ->label('Book over capacity')
                    ->helperText('This class does not have enough places. Tick to book them anyway; the capacity itself stays as it is.')
                    ->visible(fn (Get $get, ClassSession $record) => count($get('member_ids') ?? []) > $record->placesLeft())
                    ->accepted(fn (Get $get, ClassSession $record) => count($get('member_ids') ?? []) > $record->placesLeft()),
            ])
            ->action(function (array $data, ClassSession $record) {
                try {
                    $bookings = app(AdminBook::class)->handle(
                        Household::query()->findOrFail($data['household_id']),
                        $record,
                        array_map('intval', $data['member_ids']),
                        (bool) $data['charge'],
                        (bool) ($data['over_capacity'] ?? false),
                        $this->admin(),
                    );
                    Notification::make()->title('Booked '.$bookings->count().' '.str('person')->plural($bookings->count()))->success()->send();
                } catch (BookingException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    private function admin(): User
    {
        /** @var User */
        return Auth::user();
    }
}
