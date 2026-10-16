<?php

namespace App\Filament\Pages;

use App\Models\Settings as StudioSettings;
use App\Support\Phone;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The studio settings, one form (docs/05 § Settings; BR-9.1). Saving clears the `Settings::current()` cache.
 *
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 3;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(StudioSettings::current()->only(array_keys(StudioSettings::DEFAULTS)));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Studio')
                    ->columns(2)
                    ->schema([
                        TextInput::make('studio_name')->label('Studio name')->required()->maxLength(255),
                        TextInput::make('contact_email')->label('Contact email')->email()->required()->maxLength(255)
                            ->helperText('Shown on the site and used as the reply-to address of every email.'),
                        TextInput::make('contact_phone')->label('Contact phone')->tel()->rules(Phone::rules(required: false)),
                        TextInput::make('admin_notification_emails')->label('Admin notification emails')->required()->maxLength(500)
                            ->helperText('Who gets the admin emails. Separate several addresses with commas.')
                            ->rules([fn (): Closure => $this->commaSeparatedEmails()]),
                    ]),
                Section::make('Booking')
                    ->columns(2)
                    ->schema([
                        TextInput::make('default_cutoff_hours')->label('Booking cutoff (hours before a class)')->numeric()->integer()->required()->minValue(0)->maxValue(48)
                            ->helperText('A class can be booked and cancelled with a credit back until this many hours before it starts. A class can override it.'),
                        TextInput::make('booking_horizon_days')->label('Booking window (days)')->numeric()->integer()->required()->minValue(1)->maxValue(31)
                            ->helperText('How far ahead customers can book, within the current month.'),
                    ]),
                Section::make('Legal and payment')
                    ->schema([
                        TextInput::make('terms_version')->label('Terms version')->required()->maxLength(7)->regex('/^\d{4}-(0[1-9]|1[0-2])$/')
                            ->placeholder('2026-11')
                            ->helperText('Year and month of the current Terms and Privacy Policy, like 2026-11. New sign-ups record it.'),
                        Textarea::make('payment_instructions')->label('Payment instructions')->rows(5)
                            ->helperText('Bank details for EFT payments. They go in the email that confirms a package.'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save settings')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['admin_notification_emails'] = implode(', ', $this->emails((string) $data['admin_notification_emails']));

        // A model save, so the cached copy is dropped (docs/03 § settings).
        StudioSettings::query()->firstOrFail()->update($data);

        Notification::make()->title('Settings saved')->success()->send();
    }

    /**
     * @return list<string>
     */
    private function emails(string $list): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $list))));
    }

    private function commaSeparatedEmails(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $emails = $this->emails((string) $value);

            if ($emails === []) {
                $fail('Enter at least one email address.');
            }

            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $fail("\"{$email}\" is not a valid email address.");
                }
            }
        };
    }
}
