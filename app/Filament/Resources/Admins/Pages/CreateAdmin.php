<?php

namespace App\Filament\Resources\Admins\Pages;

use App\Enums\Role;
use App\Filament\Resources\Admins\AdminResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

// BR-9.4: an admin creates another admin, with a password or a set-password link by email.
class CreateAdmin extends CreateRecord
{
    protected static string $resource = AdminResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $sendLink = (bool) $data['send_link'];

        $admin = new User([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            // With a link the admin picks the password themselves; until then nobody can guess this one.
            'password' => $sendLink ? Str::random(40) : $data['password'],
        ]);
        $admin->role = Role::Admin;
        $admin->email_verified_at = now();
        $admin->save();

        if ($sendLink) {
            Password::sendResetLink(['email' => $admin->email]);
        }

        return $admin;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()->success()->title('Admin created');
    }
}
