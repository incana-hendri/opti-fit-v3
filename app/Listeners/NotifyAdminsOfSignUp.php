<?php

namespace App\Listeners;

use App\Mail\NewSignUp;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Mail;

// Email A1 (docs/06) goes out once the new customer has verified their email.
class NotifyAdminsOfSignUp
{
    public function handle(Verified $event): void
    {
        $user = $event->user;
        $recipients = Settings::current()->adminNotificationEmails();

        if (! $user instanceof User || $user->isAdmin() || $recipients === []) {
            return;
        }

        Mail::to($recipients)->queue(new NewSignUp($user));
    }
}
