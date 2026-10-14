<?php

use App\Mail\DeletionRequested;
use App\Models\Settings;
use App\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

it('renders the home and account pages', function (string $path) {
    $this->actingAs(customer())->get($path)->assertOk();
})->with(['/home', '/account']);

it('updates the profile and marketing opt-in', function () {
    $user = customer();

    $this->actingAs($user)->put('/user/profile-information', [
        'first_name' => 'Anna',
        'last_name' => 'Jones',
        'email' => $user->email,
        'phone' => '082 000 1111',
        'marketing_opt_in' => '1',
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->last_name)->toBe('Jones')
        ->and($user->phone)->toBe('082 000 1111')
        ->and($user->marketing_opt_in)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

it('asks for re-verification when the email changes (BR-1.3)', function () {
    Notification::fake();
    $user = customer();

    $this->actingAs($user)->put('/user/profile-information', [
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => 'new@example.com',
        'phone' => $user->phone,
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('changes the password', function () {
    $user = customer();

    $this->actingAs($user)->put('/user/password', [
        'current_password' => 'password',
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('a-new-password', $user->fresh()->password))->toBeTrue();
});

it('records a deletion request and emails the admins once (BR-1.6, A4)', function () {
    Mail::fake();
    Settings::query()->first()->update(['admin_notification_emails' => 'owner@opti-fit.co.za, coach@opti-fit.co.za']);
    $user = customer();

    $this->actingAs($user)->post('/account/deletion-request')->assertRedirect(route('account'));
    $this->actingAs($user)->post('/account/deletion-request');

    expect($user->fresh()->deletion_requested_at)->not->toBeNull();
    Mail::assertQueued(DeletionRequested::class, 1);
    Mail::assertQueued(DeletionRequested::class, fn (DeletionRequested $mail) => $mail->hasTo('owner@opti-fit.co.za')
        && $mail->hasTo('coach@opti-fit.co.za')
        && $mail->household->is($user->household));

    $this->actingAs($user)->get('/account')->assertSee('Requested on');
});
