<?php

namespace App\Filament\Portal\Pages\Auth;

use App\Models\User;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use App\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected static string $view = 'filament.portal.pages.auth.login';

    public function getHeading(): \Illuminate\Contracts\Support\Htmlable|string
    {
        $name = app('tenant')?->name;
        if ($name) {
            return new \Illuminate\Support\HtmlString(
                '<span style="display:block;font-size:0.85rem;font-weight:500;opacity:0.6;margin-bottom:0.15rem;">Sign in to</span>' .
                '<span>' . e($name) . '</span>'
            );
        }
        return 'Sign in';
    }

    public function mount(): void
    {
        parent::mount();

        if (request()->query('registered')) {
            Notification::make()
                ->title('Registration submitted')
                ->body('A verification email has been sent to your address. Once verified, your account will be reviewed and you will be notified when access is granted.')
                ->info()
                ->persistent()
                ->send();
        }

        if (request()->query('reason') === 'session_expired') {
            Notification::make()
                ->title('Session expired')
                ->body('Your session has expired. Please log in again.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    public function authenticate(): ?LoginResponse
    {
        $data     = $this->form->getState();
        $email    = Str::lower($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $orgId    = app('tenant')?->id;
        $user     = User::where('email', $email)->where('organisation_id', $orgId)->first();

        if ($user && $user->isLocked()) {
            $minutes = (int) ceil(now()->diffInSeconds($user->locked_until) / 60);
            throw ValidationException::withMessages([
                'data.email' => "Account locked after too many failed attempts. Try again in {$minutes} minute(s).",
            ]);
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->throwFailureValidationException();
        }

        auth()->login($user, $data['remember'] ?? false);

        $response = app(LoginResponse::class);

        if ($response !== null) {
            $freshUser = User::where('email', $email)->where('organisation_id', $orgId)->first();
            Cache::forget('login_failures:' . $email);

            if ($freshUser && $freshUser->locked_until?->isPast()) {
                $freshUser->unlock();
            }

            $freshUser?->forceFill(['last_login_at' => now()])->save();

            if ($freshUser && $freshUser->isPending()) {
                auth()->logout();
                throw ValidationException::withMessages([
                    'data.email' => 'Your account is awaiting admin approval. You will be notified once access is granted.',
                ]);
            }

            if ($freshUser && $freshUser->status === 'inactive') {
                auth()->logout();
                throw ValidationException::withMessages([
                    'data.email' => 'Your account has been deactivated. Please contact us for assistance.',
                ]);
            }

            // Org portal: check tenant membership
            $tenant = app('tenant');
            if ($freshUser && $tenant) {
                $membership = $freshUser->membershipFor($tenant);
                if (! $membership) {
                    auth()->logout();
                    $this->redirect(route('filament.portal.auth.register') . '?no_membership=1', navigate: false);
                    return null;
                }
                if ($membership->status === 'pending') {
                    auth()->logout();
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'data.email' => 'Your registration is awaiting approval from the organisation administrator.',
                    ]);
                }
                if ($membership->status === 'suspended') {
                    auth()->logout();
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'data.email' => 'Your access to this organisation has been suspended.',
                    ]);
                }

                // Admins and officials go directly to the manage panel
                if ($freshUser->isOrgAdmin($tenant) || $freshUser->isOrgOfficial($tenant)) {
                    $this->redirect('/manage', navigate: false);
                    return null;
                }
            }
        }

        return $response;
    }

    protected function throwFailureValidationException(): never
    {
        $email    = Str::lower($this->form->getState()['email'] ?? '');
        $cacheKey = 'login_failures:' . $email;

        $attempts = Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $attempts, now()->addHour());

        if ($attempts >= 5) {
            $user = User::where('email', $email)->where('organisation_id', app('tenant')?->id)->first();
            $user?->lock();
        }

        parent::throwFailureValidationException();
    }
}
