<?php

use App\Models\Settings;
use Illuminate\Support\Facades\DB;

it('seeds the studio defaults (BR-9.1)', function () {
    $settings = Settings::current();

    expect($settings->studio_name)->toBe('OptiFit')
        ->and($settings->contact_email)->toBe('info@opti-fit.co.za')
        ->and($settings->default_cutoff_hours)->toBe(8)
        ->and($settings->booking_horizon_days)->toBe(14)
        ->and($settings->terms_version)->toBe('2026-11');
});

it('serves settings from the cache and refreshes it on save', function () {
    Settings::current();

    // A write that bypasses the model leaves the cached copy in place.
    DB::table('settings')->update(['default_cutoff_hours' => 6]);
    expect(Settings::current()->default_cutoff_hours)->toBe(8);

    $settings = Settings::query()->first();
    $settings->update(['default_cutoff_hours' => 12]);
    expect(Settings::current()->default_cutoff_hours)->toBe(12);
});

it('splits the admin notification emails', function () {
    Settings::query()->first()->update(['admin_notification_emails' => 'a@example.com, b@example.com ,']);

    expect(Settings::current()->adminNotificationEmails())->toBe(['a@example.com', 'b@example.com']);
});

it('runs the app and the database in South African time', function () {
    expect(config('app.timezone'))->toBe('Africa/Johannesburg')
        ->and(DB::scalar('SELECT @@session.time_zone'))->toBe('+02:00');
});
