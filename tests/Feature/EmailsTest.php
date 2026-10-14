<?php

use App\Mail\DeletionRequested;
use App\Mail\NewSignUp;
use App\Models\Settings;
use App\Models\User;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

it('emails the admins when a customer verifies their email (A1)', function () {
    Mail::fake();
    $user = User::factory()->unverified()->create();

    event(new Verified($user));

    Mail::assertQueued(NewSignUp::class, 1);
    Mail::assertQueued(NewSignUp::class, fn (NewSignUp $mail) => $mail->hasTo('info@opti-fit.co.za') && $mail->user->is($user));
});

it('sends no sign-up email for admins or when no admin address is set (A1)', function () {
    Mail::fake();

    event(new Verified(admin()));
    Settings::query()->first()->update(['admin_notification_emails' => null]);
    event(new Verified(User::factory()->create()));

    Mail::assertNothingQueued();
});

it('renders the admin emails with their subjects (A1, A4)', function () {
    $user = customer();
    $user->forceFill(['deletion_requested_at' => now()])->save();

    expect((new NewSignUp($user))->envelope()->subject)->toBe('New OptiFit sign-up: '.$user->fullName())
        ->and((new NewSignUp($user))->render())->toContain($user->email)
        ->and((new DeletionRequested($user->household))->envelope()->subject)->toBe('Deletion request: '.$user->household->name)
        ->and((new DeletionRequested($user->household))->render())->toContain('Anonymise household');
});

it('brands the customer auth emails (C1, C2)', function () {
    $user = customer();

    $verify = (new VerifyEmail)->toMail($user);
    $reset = (new ResetPassword('token'))->toMail($user);

    expect($verify->subject)->toBe('Verify your email')
        ->and($reset->subject)->toBe('Reset your password')
        ->and((string) $verify->render())
        ->toContain('images/brand/logo-on-light-tagline.jpg')
        ->toContain('Gene Louw Activity Centre, Durbanville')
        ->toContain('#FF9500');
});

it('queues the auth emails (docs/06)', function () {
    expect(new VerifyEmail)->toBeInstanceOf(ShouldQueue::class)
        ->and(new ResetPassword('token'))->toBeInstanceOf(ShouldQueue::class);
});
