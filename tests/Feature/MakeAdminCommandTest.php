<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a verified admin from the prompts (BR-9.4)', function () {
    $this->artisan('optifit:make-admin', ['email' => 'owner@opti-fit.co.za'])
        ->expectsQuestion('First name', 'Lize')
        ->expectsQuestion('Last name', 'Owner')
        ->expectsQuestion('Password', 'secret-password')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'owner@opti-fit.co.za')->sole();

    expect($admin->role)->toBe(Role::Admin)
        ->and($admin->first_name)->toBe('Lize')
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('secret-password', $admin->password))->toBeTrue();
});

it('refuses an email that is already taken', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('optifit:make-admin', ['email' => 'taken@example.com'])->assertFailed();

    expect(User::query()->where('role', Role::Admin)->count())->toBe(0);
});

it('refuses an invalid email', function () {
    $this->artisan('optifit:make-admin', ['email' => 'not-an-email'])->assertFailed();
});
