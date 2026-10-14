<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

// Email C1 (docs/06).
class VerifyEmail extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your email')
            ->greeting('Welcome to OptiFit!')
            ->line('Please confirm your email address so you can add your family and choose a package.')
            ->action('Verify my email', $url)
            ->line("If you didn't sign up for OptiFit, you can ignore this email.");
    }
}
