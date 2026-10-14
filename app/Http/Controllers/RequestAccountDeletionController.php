<?php

namespace App\Http\Controllers;

use App\Mail\DeletionRequested;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

// BR-1.6: record the request and tell the admins. An admin anonymises the household (BR-9.6).
class RequestAccountDeletionController
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->deletion_requested_at === null) {
            $user->forceFill(['deletion_requested_at' => now()])->save();

            $recipients = Settings::current()->adminNotificationEmails();
            if ($recipients !== []) {
                Mail::to($recipients)->queue(new DeletionRequested($user->household));
            }
        }

        return redirect()->route('account')
            ->with('toast', ['message' => "We've received your request. The studio will be in touch.", 'type' => 'success']);
    }
}
