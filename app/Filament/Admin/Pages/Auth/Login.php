<?php

namespace App\Filament\Admin\Pages\Auth;

use App\Models\User;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

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

        // Successful login — clear lockout state and stamp last_login_at
        if ($response !== null) {
            Cache::forget('login_failures:' . $email);
            if ($user && $user->locked_until?->isPast()) {
                $user->unlock();
            }
            $user?->forceFill(['last_login_at' => now()])->save();
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
