<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;

// BR-2.7: a customer only sees and changes their own household. Admins manage all of them.
class HouseholdPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() && ! $user->isDeactivated() ? true : null;
    }

    public function view(User $user, Household $household): bool
    {
        return $household->user_id === $user->id;
    }

    public function update(User $user, Household $household): bool
    {
        return $household->user_id === $user->id;
    }
}
