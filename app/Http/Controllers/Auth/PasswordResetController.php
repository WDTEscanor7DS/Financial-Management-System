<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

/**
 * Section 36: reset tokens are generated, hashed, and expired entirely by
 * Laravel's password broker (config('auth.passwords.users.expire'),
 * default 60 minutes) -- this controller never generates or stores a token
 * itself, and never emails/returns a raw password.
 */
class PasswordResetController extends Controller
{
    public function requestLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        AuditService::log(
            'Password Reset Requested',
            'Security & Audit',
            null,
            'Password reset requested for '.$request->input('email')
        );

        // Same response whether or not the email exists, to avoid leaking
        // which addresses have accounts.
        return back()->with('status', __($status));
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::min(10)->mixedCase()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                AuditService::log('Password Reset', 'Security & Audit', (string) $user->id, $user->name.'\'s password was reset.');
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
