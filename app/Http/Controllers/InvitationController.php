<?php

namespace App\Http\Controllers;

use App\Models\OrganisationMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class InvitationController extends Controller
{
    public function accept(Request $request, OrganisationMembership $membership)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This invitation link has expired or is invalid.');
        }

        if ($membership->status !== 'invited') {
            return redirect($this->landingUrl($membership))
                ->with('info', 'This invitation has already been accepted.');
        }

        $user = $membership->user;
        $org  = $membership->organisation;

        if ($user->password !== null) {
            // Existing user: activate membership immediately and redirect to the right panel
            $membership->update(['status' => 'active', 'joined_at' => now()]);

            return redirect($this->landingUrl($membership))
                ->with('success', "You have been added to {$org->name}. Please log in to continue.");
        }

        // New user: store intent in session then show setup form
        session(['invite_membership_id' => $membership->id]);

        return view('invite.accept', compact('membership', 'org', 'user'));
    }

    public function complete(Request $request, OrganisationMembership $membership)
    {
        abort_if(session('invite_membership_id') !== $membership->id, 403);

        if ($membership->status !== 'invited') {
            return redirect($this->landingUrl($membership));
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $membership->user;
        $org  = $membership->organisation;

        $user->update([
            'password'          => Hash::make($validated['password']),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $membership->update(['status' => 'active', 'joined_at' => now()]);

        Auth::login($user);

        session()->forget('invite_membership_id');

        return redirect($this->landingUrl($membership));
    }

    private function landingUrl(OrganisationMembership $membership): string
    {
        $org    = $membership->organisation;
        $domain = config('app.domain', 'kompetic.com');
        $base   = config('app.scheme') . '://' . $org->slug . '.' . $domain;

        return match ($membership->role) {
            'competitor' => $base . '/portal',
            default      => $base . '/manage',
        };
    }
}
