<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

// BR-9.4
class MakeAdmin extends Command
{
    protected $signature = 'optifit:make-admin {email}';

    protected $description = 'Create an admin user who can sign in at /admin';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error("A user with {$email} already exists.");

            return self::FAILURE;
        }

        $firstName = text('First name', required: true);
        $lastName = text('Last name', required: true);
        $password = password('Password', required: true, validate: fn (string $value) => strlen($value) < 8
            ? 'The password must be at least 8 characters.'
            : null);

        $user = new User([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $password,
        ]);
        $user->role = Role::Admin;
        $user->email_verified_at = now();
        $user->save();

        $this->info("Admin {$email} created. Sign in at ".url('/admin'));

        return self::SUCCESS;
    }
}
