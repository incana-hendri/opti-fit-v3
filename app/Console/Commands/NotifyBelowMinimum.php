<?php

namespace App\Console\Commands;

use App\Mail\AdminBelowMinimum;
use App\Models\ClassSession;
use App\Models\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

// A5 (BR-7.4, docs/06): at 06:00 the admins hear about today's sessions that are below their minimum (those not started yet). Silent when none are.
class NotifyBelowMinimum extends Command
{
    protected $signature = 'optifit:below-minimum';

    protected $description = 'Email the admins the classes today that are below their minimum participants';

    public function handle(): int
    {
        $below = ClassSession::query()
            ->scheduled()
            ->withOccupancy()
            ->whereNotNull('min_participants')
            ->whereBetween('starts_at', [now(), today()->endOfDay()])
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (ClassSession $session) => $session->isBelowMinimum());

        $recipients = Settings::current()->adminNotificationEmails();

        if ($below->isEmpty() || $recipients === []) {
            $this->info('No classes below minimum today.');

            return self::SUCCESS;
        }

        Mail::to($recipients)->queue(new AdminBelowMinimum($below->values()));
        $this->info("Emailed the admins about {$below->count()} class(es) below minimum.");

        return self::SUCCESS;
    }
}
