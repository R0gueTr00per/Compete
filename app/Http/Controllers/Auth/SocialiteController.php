<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    private const ALLOWED_PROVIDERS = ['facebook', 'google', 'microsoft'];

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::ALLOWED_PROVIDERS), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::ALLOWED_PROVIDERS), 404);

        $socialUser = Socialite::driver($provider)->user();

        // 1. Look up existing social account
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            $socialAccount->update([
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
            ]);

            Auth::login($socialAccount->user, remember: true);

            return $this->redirectAfterLogin();
        }

        // 2. Look up user by verified email and link social account
        if ($socialUser->getEmail()) {
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                $user->socialAccounts()->create([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
                ]);

                Auth::login($user, remember: true);

                return $this->redirectAfterLogin();
            }
        }

        // 3. Create new user with social account (password=null for social-only accounts)
        $user = User::create([
            'email' => $socialUser->getEmail(),
            'password' => null,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('user');

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'token_expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
        ]);

        Auth::login($user, remember: true);

        return redirect()->route('profile.complete');
    }

    private function redirectAfterLogin(): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->competitorProfile?->profile_complete) {
            return redirect()->route('profile.complete');
        }

        return redirect()->intended('/portal');
    }
}
