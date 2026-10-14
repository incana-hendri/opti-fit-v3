<?php

namespace App\Filament\Resources\Memberships\Actions;

use App\Actions\Memberships\ApproveMembership;
use App\Actions\Memberships\CancelMembershipNow;
use App\Actions\Memberships\DeclineMembership;
use App\Actions\Memberships\PauseMembership;
use App\Actions\Memberships\ResumeMembership;
use App\Actions\Memberships\UpdateMembership;
use App\Enums\MembershipStatus;
use App\Exceptions\MembershipException;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use App\Support\Money;
use App\Support\Period;
use App\Support\Proration;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * The membership row actions (docs/05). Each one only collects input and calls the matching action class.
 */
class MembershipActions
{
    /** BR-4.4: a request approved after the 20th defaults to starting next month. */
    private const LAST_DAY_FOR_CURRENT_MONTH_START = 20;

    // BR-4.4, 4.5, 4.7
    public static function approve(): Action
    {
        return Action::make('approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalHeading(fn (Membership $record) => "Approve {$record->household->name}")
            ->modalSubmitActionLabel('Approve')
            ->visible(fn (Membership $record) => $record->status === MembershipStatus::Requested)
            ->fillForm(fn (Membership $record) => [
                'starts_period' => self::isChange($record) || now()->day > self::LAST_DAY_FOR_CURRENT_MONTH_START ? Period::next() : Period::current(),
                'monthly_credits' => $record->monthly_credits,
                'monthly_price' => $record->monthly_price_cents / 100,
                'first_month_credits' => Proration::firstMonthCredits($record->monthly_credits, now()),
            ])
            ->schema(fn (Membership $record) => [
                Radio::make('starts_period')
                    ->label('Starts')
                    ->options([
                        Period::current() => 'This month ('.Period::label(Period::current()).')',
                        Period::next() => 'Next month ('.Period::label(Period::next()).')',
                    ])
                    ->live()
                    ->disabled(self::isChange($record))
                    ->helperText(self::isChange($record)
                        ? 'This changes an existing package, so it starts next month. Current package ends '.Period::lastDay(Period::current())->format('j M').'.'
                        : null),
                TextInput::make('monthly_credits')
                    ->numeric()->integer()->required()->minValue(1)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, mixed $state) => $set('first_month_credits', Proration::firstMonthCredits(max(1, (int) $state), now()))),
                TextInput::make('monthly_price')
                    ->label('Monthly price')
                    ->prefix('R')
                    ->numeric()->required()->minValue(0)->step(0.01)
                    ->live(onBlur: true),
                TextInput::make('first_month_credits')
                    ->label('Credits for the rest of this month')
                    ->numeric()->integer()->minValue(0)
                    ->visible(fn (Get $get) => ! self::isChange($record) && $get('starts_period') === Period::current())
                    ->helperText(fn (Get $get) => 'Prorated for the days left. Suggested price for the rest of this month: '
                        .Money::format(Proration::suggestedPriceCents(Money::toCents($get('monthly_price') ?? 0), now())).' (for information only).'),
                Textarea::make('admin_note')
                    ->label('Admin note')
                    ->helperText('For example the payment reference.')
                    ->rows(2),
            ])
            ->action(fn (Membership $record, array $data) => self::attempt(
                fn () => app(ApproveMembership::class)->handle(
                    $record,
                    self::admin(),
                    $data['starts_period'] ?? Period::next(),
                    (int) $data['monthly_credits'],
                    Money::toCents($data['monthly_price']),
                    isset($data['first_month_credits']) ? (int) $data['first_month_credits'] : null,
                    $data['admin_note'] ?? null,
                ),
                'Membership approved',
            ));
    }

    // BR-4.6
    public static function decline(): Action
    {
        return Action::make('decline')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->modalHeading(fn (Membership $record) => "Decline {$record->household->name}")
            ->modalSubmitActionLabel('Decline')
            ->visible(fn (Membership $record) => $record->status === MembershipStatus::Requested)
            ->schema([
                Textarea::make('reason')
                    ->required()->maxLength(500)->rows(3)
                    ->helperText('The customer receives this reason by email.'),
            ])
            ->action(fn (Membership $record, array $data) => self::attempt(
                fn () => app(DeclineMembership::class)->handle($record, $data['reason']),
                'Request declined',
            ));
    }

    // BR-4.9: the change applies from the next grant.
    public static function edit(): Action
    {
        return Action::make('edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->modalHeading(fn (Membership $record) => "Edit {$record->household->name}")
            ->visible(fn (Membership $record) => self::isRunning($record))
            ->fillForm(fn (Membership $record) => [
                'monthly_credits' => $record->monthly_credits,
                'monthly_price' => $record->monthly_price_cents / 100,
                'member_ids' => $record->members->pluck('id')->all(),
                'admin_note' => $record->admin_note,
            ])
            ->schema(fn (Membership $record) => [
                TextInput::make('monthly_credits')->numeric()->integer()->required()->minValue(1),
                TextInput::make('monthly_price')->label('Monthly price')->prefix('R')->numeric()->required()->minValue(0)->step(0.01),
                CheckboxList::make('member_ids')
                    ->label('Covered members')
                    ->options($record->household->activeMembers()->oldest()->get()->mapWithKeys(fn (Member $member) => [$member->id => $member->fullName()]))
                    ->required(),
                Textarea::make('admin_note')->label('Admin note')->rows(2),
            ])
            ->modalDescription('Changes apply from the next monthly grant. Credits already granted stay as they are.')
            ->action(fn (Membership $record, array $data) => self::attempt(
                fn () => app(UpdateMembership::class)->handle(
                    $record,
                    (int) $data['monthly_credits'],
                    Money::toCents($data['monthly_price']),
                    array_map('intval', $data['member_ids']),
                    $data['admin_note'] ?? null,
                ),
                'Membership updated',
            ));
    }

    // BR-4.9
    public static function pause(): Action
    {
        return Action::make('pause')
            ->icon(Heroicon::OutlinedPauseCircle)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('No new credits are granted while a membership is paused.')
            ->visible(fn (Membership $record) => $record->status === MembershipStatus::Active)
            ->action(fn (Membership $record) => self::attempt(fn () => app(PauseMembership::class)->handle($record), 'Membership paused'));
    }

    // BR-4.9
    public static function resume(): Action
    {
        return Action::make('resume')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Credits start again on the next 1st, unless this month was already granted.')
            ->visible(fn (Membership $record) => $record->status === MembershipStatus::Paused)
            ->action(fn (Membership $record) => self::attempt(fn () => app(ResumeMembership::class)->handle($record), 'Membership resumed'));
    }

    // BR-4.9
    public static function cancelNow(): Action
    {
        return Action::make('cancelNow')
            ->label('Cancel now')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Credits already granted stay usable until the month ends. No new credits follow.')
            ->visible(fn (Membership $record) => self::isRunning($record))
            ->action(fn (Membership $record) => self::attempt(fn () => app(CancelMembershipNow::class)->handle($record), 'Membership cancelled'));
    }

    // BR-4.7: a household that already has a package in effect is asking for a change.
    private static function isChange(Membership $membership): bool
    {
        return $membership->household->membershipInEffect() !== null;
    }

    private static function isRunning(Membership $membership): bool
    {
        return in_array($membership->status, [MembershipStatus::Active, MembershipStatus::Paused], true);
    }

    private static function attempt(Closure $work, string $success): void
    {
        try {
            $work();
            Notification::make()->title($success)->success()->send();
        } catch (MembershipException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    private static function admin(): User
    {
        /** @var User */
        return Auth::user();
    }
}
