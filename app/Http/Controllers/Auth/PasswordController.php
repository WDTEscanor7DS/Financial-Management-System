<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Section 37: requires the current password, a confirmed new password
     * meeting strength rules, updates the hash, logs the user's other
     * sessions out, and writes an audit entry -- all server-side.
     */
    public function update(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($request->input('password'))])->save();

        // Invalidate every other session for this user (logged in on
        // another device, etc.) while keeping the current one alive.
        Auth::logoutOtherDevices($request->input('current_password'));

        AuditService::log('Password Changed', 'Security & Audit', (string) $user->id, $user->name . ' changed their password.');

        return back()->with('status', 'Your password has been updated.');
    }
}
