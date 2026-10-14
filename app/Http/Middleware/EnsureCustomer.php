<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer pages: role customer, not deactivated, onboarding finished (docs/07 § Conventions).
 */
class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        // BR-1.7
        if ($user->isAdmin()) {
            return redirect('/admin');
        }

        // BR-1.8: a deactivated user loses an existing session too.
        if ($user->isDeactivated()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This account has been deactivated. Please contact the studio.']);
        }

        if (! $request->routeIs('onboarding') && ! $user->household?->isOnboarded()) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
