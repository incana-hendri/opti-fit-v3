<?php

namespace App\Actions\Admins;

use App\Enums\Role;
use App\Exceptions\AccountException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// BR-1.8, 9.4 (docs/05 § Admins): deactivate an admin, never yourself and never the last active admin.
class DeactivateAdmin
{
    public function handle(User $admin, User $by): User
    {
        return DB::transaction(function () use ($admin, $by) {
            // Lock every admin, so two admins deactivating each other at once can't leave nobody.
            $admins = User::query()->where('role', Role::Admin)->orderBy('id')->lockForUpdate()->get();
            $admin = $admins->firstWhere('id', $admin->id) ?? throw new AccountException('That admin no longer exists.');

            if ($admin->id === $by->id) {
                throw new AccountException("You can't deactivate your own login.");
            }

            if ($admins->whereNull('deactivated_at')->except([$admin->id])->isEmpty()) {
                throw new AccountException('There must always be at least one active admin.');
            }

            $admin->forceFill(['deactivated_at' => now()])->save();

            return $admin;
        });
    }
}
