<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalVerifyEmailController
{
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->route('portal.email-verified');
    }
}
