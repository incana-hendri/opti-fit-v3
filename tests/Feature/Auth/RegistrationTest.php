<?php

use App\Mail\NewSignUp;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function registration(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Anna',
        'last_name' => 'Smith',
        'email' => 'anna@example.com',
        'phone' => '082 123 4567',
        'date_of_birth' => '1986-04-02',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'terms' => '1',
    ], $overrides);
}

it('registers a customer and sends the verification email (BR-1.1, C1)', function () {
    Notification::fake();

    $this->post('/register', registration())->assertRedirect(route('home'));

    $user = User::query()->where('email', 'anna@example.com')->sole();
    expect($user->isAdmin())->toBeFalse()
        ->and($user->phone)->toBe('082 123 4567')
        ->and($user->date_of_birth->toDateString())->toBe('1986-04-02')
        ->and($user->marketing_opt_in)->toBeFalse()
        ->and($user->email_verified_at)->toBeNull();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('verifies through the emailed link, tells the admins, and moves on to onboarding (BR-1.3, A1)', function () {
    Mail::fake();
    $this->post('/register', registration());
    $user = User::query()->sole();

    $this->get(route('home'))->assertRedirect(route('verification.notice'));

    $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);
    $this->get($link)->assertRedirect(route('home'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Mail::assertQueued(NewSignUp::class, 1);
    $this->get(route('home'))->assertRedirect(route('onboarding'));
});

it('stores the accepted terms version and time (BR-1.4)', function () {
    $this->travelTo('2026-11-03 09:15:00');

    $this->post('/register', registration());

    $user = User::query()->sole();
    expect($user->terms_version)->toBe('2026-11')
        ->and($user->terms_accepted_at->toDateTimeString())->toBe('2026-11-03 09:15:00');
});

it('records the optional marketing opt-in (BR-1.1)', function () {
    $this->post('/register', registration(['marketing_opt_in' => '1']));

    expect(User::query()->sole()->marketing_opt_in)->toBeTrue();
});

it('requires every registration field (BR-1.1)', function (string $field) {
    $this->post('/register', registration([$field => '']))->assertSessionHasErrors($field);

    expect(User::query()->count())->toBe(0);
})->with(['first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'password', 'terms']);

it('rejects invalid registration values', function (string $field, string $value) {
    $this->post('/register', registration([$field => $value]))->assertSessionHasErrors($field);
})->with([
    'phone with letters' => ['phone', '082 ABC 4567'],
    'phone too short' => ['phone', '12345'],
    'bad email' => ['email', 'not-an-email'],
    'short password' => ['password', 'short'],
]);

it('requires the password to be confirmed (BR-1.1)', function () {
    $this->post('/register', registration(['password_confirmation' => 'something-else']))
        ->assertSessionHasErrors('password');
});

it('rejects an email that is already registered', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    $this->post('/register', registration())->assertSessionHasErrors('email');
});

it('rejects account holders under 18 (BR-1.2)', function () {
    $this->travelTo('2026-11-03');

    $this->post('/register', registration(['date_of_birth' => '2008-11-04']))
        ->assertSessionHasErrors(['date_of_birth' => 'The account holder must be 18 or older.']);

    expect(User::query()->count())->toBe(0);
});

it('accepts an account holder who turns 18 today (BR-1.2)', function () {
    $this->travelTo('2026-11-03');

    $this->post('/register', registration(['date_of_birth' => '2008-11-03']))->assertSessionHasNoErrors();
});
