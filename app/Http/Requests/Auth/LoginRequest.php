<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();
        $this->ensureAccountIsNotLocked();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), 3600);
            $this->recordFailedAttempt();

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        $this->clearFailedAttempts();
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function ensureAccountIsNotLocked(): void
    {
        $email = Str::lower($this->string('email'));
        $user  = User::where('email', $email)->first();

        if ($user && $user->isLocked()) {
            $minutes = (int) ceil(now()->diffInSeconds($user->locked_until) / 60);

            throw ValidationException::withMessages([
                'email' => "This account has been locked due to too many failed login attempts. Please try again in {$minutes} minute(s).",
            ]);
        }
    }

    private function recordFailedAttempt(): void
    {
        $email = Str::lower($this->string('email'));
        $key   = 'login_failures:' . $email;

        $attempts = Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, now()->addHour());

        if ($attempts >= 5) {
            $user = User::where('email', $email)->first();
            $user?->lock();
        }
    }

    private function clearFailedAttempts(): void
    {
        $email = Str::lower($this->string('email'));
        Cache::forget('login_failures:' . $email);

        // Clear any DB-level lock set by a previous lockout
        $user = User::where('email', $email)->first();
        if ($user && $user->locked_until?->isPast()) {
            $user->unlock();
        }
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
