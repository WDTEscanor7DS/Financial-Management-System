<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Section 35: an Administrator disabling/suspending a user must take
 * effect immediately, not just on that user's next login. Because sessions
 * are stored in the database (see the `sessions` migration), a user whose
 * status changes mid-session will fail this check on their very next
 * request and be forced back to /login.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Your account has been disabled. Contact an Administrator.');
        }

        return $next($request);
    }
}
