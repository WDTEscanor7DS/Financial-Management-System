<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request)
    {
        $request->authenticate();

        // Section 31: regenerate the session ID on every successful login
        // to prevent session fixation -- an attacker who fixed a session ID
        // before login gains nothing once this runs.
        $request->session()->regenerate();

        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();

        AuditService::log('Login', 'Security & Audit', null, $user->name.' ('.$user->role->name.') signed in.');

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            AuditService::log('Logout', 'Security & Audit', null, $user->name.' ('.$user->role->name.') signed out.');
        }

        Auth::guard('web')->logout();

        // Section 62: invalidate the session and rotate the CSRF token so
        // the browser Back button cannot replay a page from the
        // authenticated session, and no stale token survives into the next
        // login.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
