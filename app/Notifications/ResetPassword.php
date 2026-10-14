<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

// Email C2 (docs/06).
class ResetPassword extends BaseResetPassword implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Reset your password')
            ->greeting('Hi there,')
            ->line('We received a request to reset the password for your OptiFit account.')
            ->action('Choose a new password', $url)
            ->line("This link expires in {$minutes} minutes. If you didn't ask for it, you can ignore this email.");
    }
}
