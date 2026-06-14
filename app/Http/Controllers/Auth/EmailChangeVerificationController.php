<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailChangeVerificationController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! $user->pending_email) {
            return redirect()->route('filament.portal.pages.security')
                ->with('email_change_error', 'No pending email change found. It may have already been confirmed or cancelled.');
        }

        if (! hash_equals(sha1($user->pending_email), $hash)) {
            abort(403);
        }

        // Re-validate uniqueness at verification time in case the address was claimed
        $conflict = User::where('email', $user->pending_email)
            ->where('organisation_id', $user->organisation_id)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($conflict) {
            $user->update(['pending_email' => null]);
            return redirect()->route('filament.portal.pages.security')
                ->with('email_change_error', 'That email address is already in use. Please request a new change.');
        }

        $user->forceFill([
            'email'             => $user->pending_email,
            'pending_email'     => null,
            'email_verified_at' => now(),
        ])->save();

        if (auth()->check() && auth()->id() === $user->id) {
            return redirect()->route('filament.portal.pages.security')
                ->with('email_change_verified', true);
        }

        return redirect()->route('filament.portal.auth.login')
            ->with('status', 'Email address updated. Please log in with your new address.');
    }
}
