<?php

use App\Filament\Pages\Settings;
use App\Models\Settings as StudioSettings;
use Livewire\Livewire;

// docs/05 § Settings, BR-9.1
beforeEach(function () {
    $this->actingAs(admin());
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingsForm(array $overrides = []): array
{
    return [...StudioSettings::DEFAULTS, 'contact_phone' => '021 555 0100', ...$overrides];
}

it('renders for an admin and is forbidden for a customer', function () {
    $this->get('/admin/settings')->assertOk()->assertSee('Studio name')->assertSee('Payment instructions');

    $this->actingAs(customer())->get('/admin/settings')->assertForbidden();
});

it('fills the form from the current settings', function () {
    Livewire::test(Settings::class)
        ->assertFormSet(['studio_name' => 'OptiFit', 'default_cutoff_hours' => 8, 'booking_horizon_days' => 14, 'terms_version' => '2026-11']);
});

it('saves every BR-9.1 field and the next Settings::current() call sees it', function () {
    expect(StudioSettings::current()->default_cutoff_hours)->toBe(8);

    Livewire::test(Settings::class)
        ->fillForm(settingsForm([
            'studio_name' => 'OptiFit Durbanville',
            'contact_email' => 'hello@opti-fit.co.za',
            'admin_notification_emails' => ' a@example.com ,b@example.com,',
            'default_cutoff_hours' => 6,
            'booking_horizon_days' => 21,
            'terms_version' => '2027-01',
            'payment_instructions' => "FNB\nAcc 123",
        ]))
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Settings saved');

    $settings = StudioSettings::current();

    expect($settings->studio_name)->toBe('OptiFit Durbanville')
        ->and($settings->contact_email)->toBe('hello@opti-fit.co.za')
        ->and($settings->contact_phone)->toBe('021 555 0100')
        ->and($settings->adminNotificationEmails())->toBe(['a@example.com', 'b@example.com'])
        ->and($settings->default_cutoff_hours)->toBe(6)
        ->and($settings->booking_horizon_days)->toBe(21)
        ->and($settings->terms_version)->toBe('2027-01')
        ->and($settings->payment_instructions)->toBe("FNB\nAcc 123");
});

it('validates the fields', function (array $overrides, string $field) {
    Livewire::test(Settings::class)
        ->fillForm(settingsForm($overrides))
        ->call('save')
        ->assertHasFormErrors([$field]);

    expect(StudioSettings::current()->default_cutoff_hours)->toBe(8);
})->with([
    'studio name' => [['studio_name' => ''], 'studio_name'],
    'contact email' => [['contact_email' => 'nope'], 'contact_email'],
    'a bad notification email' => [['admin_notification_emails' => 'a@example.com, not-an-email'], 'admin_notification_emails'],
    'no notification email' => [['admin_notification_emails' => ' , '], 'admin_notification_emails'],
    'cutoff too high' => [['default_cutoff_hours' => 49], 'default_cutoff_hours'],
    'cutoff negative' => [['default_cutoff_hours' => -1], 'default_cutoff_hours'],
    'horizon zero' => [['booking_horizon_days' => 0], 'booking_horizon_days'],
    'horizon too long' => [['booking_horizon_days' => 32], 'booking_horizon_days'],
    'terms version format' => [['terms_version' => '2026-13'], 'terms_version'],
    'terms version text' => [['terms_version' => 'v2'], 'terms_version'],
    'phone' => [['contact_phone' => 'call me'], 'contact_phone'],
]);

it('accepts the limits of the ranges', function () {
    Livewire::test(Settings::class)
        ->fillForm(settingsForm(['default_cutoff_hours' => 0, 'booking_horizon_days' => 31]))
        ->call('save')
        ->assertHasNoFormErrors();

    expect(StudioSettings::current()->default_cutoff_hours)->toBe(0);
});
