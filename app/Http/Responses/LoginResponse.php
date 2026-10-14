<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

// BR-1.7: admins who log in at /login land in the admin panel.
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect('/admin');
        }

        return redirect()->intended(route('home'));
    }
}
