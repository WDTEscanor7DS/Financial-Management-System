<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Section 30/61: throttles login attempts per email+IP combination (5 per
 * minute) before Laravel even attempts to check the password, and records
 * an audit entry either way. The generic error message on failure never
 * reveals whether the email exists (Section 28).
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')) . '|' . $this->ip());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            \App\Services\AuditService::log(
                'Login Failed',
                'Security & Audit',
                null,
                'Failed login attempt for ' . $this->input('email'),
                status: 'Failed'
            );

            // Deliberately generic -- never "email exists but password
            // wrong" (Section 28).
            throw ValidationException::withMessages([
                'email' => 'Invalid email or password.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account is not active. Contact an Administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }
}
