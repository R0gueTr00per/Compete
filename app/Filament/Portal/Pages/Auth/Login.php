<?php

namespace App\Filament\Portal\Pages\Auth;

use App\Models\User;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected static string $view = 'filament.portal.pages.auth.login';

    public function mount(): void
    {
        parent::mount();

        if (request()->query('registered')) {
            Notification::make()
                ->title('Registration submitted')
                ->body('Your account is awaiting admin approval. You will receive an email once access is granted.')
                ->info()
                ->persistent()
                ->send();
        }
    }

    public function authenticate(): ?LoginResponse
    {
        $data  = $this->form->getState();
        $email = Str::lower($data['email'] ?? '');
        $user  = User::where('email', $email)->first();

        if ($user && $user->isLocked()) {
            $minutes = (int) ceil(now()->diffInSeconds($user->locked_until) / 60);
            throw ValidationException::withMessages([
                'data.email' => "Account locked after too many failed attempts. Try again in {$minutes} minute(s).",
            ]);
        }

        $response = parent::authenticate();

        if ($response !== null) {
            $freshUser = User::where('email', $email)->first();
            Cache::forget('login_failures:' . $email);

            if ($freshUser && $freshUser->locked_until?->isPast()) {
                $freshUser->unlock();
            }

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
            $user = User::where('email', $email)->first();
            $user?->lock();
        }

        parent::throwFailureValidationException();
    }
}
