<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrganisationMembership;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class SocialiteController extends Controller
{
    private const ALLOWED_PROVIDERS = ['google'];

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::ALLOWED_PROVIDERS), 404);

        // Store the current org ID in session so the callback can restore tenant context
        if ($tenant = app('tenant')) {
            session(['oauth_tenant_id' => $tenant->id]);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::ALLOWED_PROVIDERS), 404);

        // Resolve tenant once — middleware may have already set app('tenant'),
        // or we fall back to the session ID stored before the OAuth redirect.
        if (! app('tenant') && ($id = session()->pull('oauth_tenant_id'))) {
            if ($org = \App\Models\Organisation::find($id)) {
                app()->instance('tenant', $org);
            }
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException) {
            return redirect()->route('filament.portal.auth.login')
                ->with('error', 'OAuth state mismatch — please try signing in again.');
        }

        // 1. Look up existing social account
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            $socialAccount->update([
                'token'            => $socialUser->token,
                'refresh_token'    => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
            ]);

            Auth::login($socialAccount->user, remember: true);

            return $this->redirectAfterLogin();
        }

        // 2. Look up user by verified email and link social account
        if ($socialUser->getEmail()) {
            $user = User::where('email', $socialUser->getEmail())
                ->where('organisation_id', app('tenant')?->id)
                ->first();

            if ($user) {
                $user->socialAccounts()->create([
                    'provider'         => $provider,
                    'provider_id'      => $socialUser->getId(),
                    'token'            => $socialUser->token,
                    'refresh_token'    => $socialUser->refreshToken,
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                ]);

                Auth::login($user, remember: true);

                return $this->redirectAfterLogin();
            }
        }

        // 3. Create new user — only if the provider returned a verified email
        if (! $socialUser->getEmail()) {
            return redirect()->route('filament.portal.auth.login')
                ->with('error', 'Your ' . ucfirst($provider) . ' account did not provide an email address. Please sign in with a different method.');
        }

        $tenant = app('tenant');
        $user   = User::create([
            'email'           => $socialUser->getEmail(),
            'organisation_id' => $tenant?->id,
            'status'          => 'active',
            'password'        => null,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->socialAccounts()->create([
            'provider'         => $provider,
            'provider_id'      => $socialUser->getId(),
            'token'            => $socialUser->token,
            'refresh_token'    => $socialUser->refreshToken,
            'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
        ]);

        // If registering via an org portal, create membership + profile automatically
        if ($tenant) {
            OrganisationMembership::create([
                'organisation_id' => $tenant->id,
                'user_id'         => $user->id,
                'role'            => 'competitor',
                'status'          => 'active',
                'joined_at'       => now(),
            ]);

            $user->ownedProfiles()->create([
                'organisation_id'  => $tenant->id,
                'profile_type'     => 'self',
                'profile_complete' => false,
                'is_active'        => true,
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->route('profile.complete');
    }

    private function redirectAfterLogin(): RedirectResponse
    {
        $user   = Auth::user();
        $tenant = $this->resolveOAuthTenant();

        // If an org is in context, ensure the user has membership
        if ($tenant) {
            $membership = $user->membershipFor($tenant);
            if (! $membership || $membership->status === 'suspended') {
                Auth::logout();
                return redirect()->route('filament.portal.auth.register', ['no_membership' => 1]);
            }
        }

        if (! $user->ownedProfiles()->where('profile_complete', true)->exists()) {
            return redirect()->route('profile.complete');
        }

        return redirect()->intended('/portal');
    }

    private function resolveOAuthTenant(): ?\App\Models\Organisation
    {
        // Try live middleware context first (for callback on org subdomain)
        if ($tenant = app('tenant')) {
            return $tenant;
        }

        // Fall back to session-stored ID saved during redirect()
        $id = session()->pull('oauth_tenant_id');
        if ($id) {
            return \App\Models\Organisation::find($id);
        }

        return null;
    }
}
