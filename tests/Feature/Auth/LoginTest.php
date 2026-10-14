<?php

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('logs a customer in and sends them home', function () {
    $user = customer();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('sends admins who log in at /login to the admin panel (BR-1.7)', function () {
    $admin = admin();

    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect('/admin');
});

it('redirects admins away from customer pages (BR-1.7)', function () {
    $this->actingAs(admin())->get('/home')->assertRedirect('/admin');
});

it('blocks deactivated users from logging in (BR-1.8)', function () {
    $user = User::factory()->deactivated()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out a deactivated user who still has a session (BR-1.8)', function () {
    $user = customer();
    $user->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($user)->get('/home')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('throttles login after five failed attempts (BR-1.5)', function () {
    $user = customer();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertStatus(429);

    $this->assertGuest();
});

it('keeps unverified customers on the verify notice (BR-1.3)', function (string $path) {
    $this->actingAs(User::factory()->unverified()->create())
        ->get($path)
        ->assertRedirect(route('verification.notice'));
})->with(['/home', '/onboarding', '/family', '/account']);

it('sends guests to the login page', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/home', '/onboarding', '/family', '/account']);

it('emails a password reset link (C2)', function () {
    Notification::fake();
    $user = customer();

    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('renders the auth pages', function (string $path) {
    $this->get($path)->assertOk()->assertSee('OptiFit');
})->with(['/login', '/register', '/forgot-password', '/reset-password/some-token?email=a@example.com']);

it('renders the verify notice for an unverified user', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get('/email/verify')
        ->assertOk()
        ->assertSee('Check your inbox');
});
