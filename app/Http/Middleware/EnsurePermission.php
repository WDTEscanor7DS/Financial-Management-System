<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level RBAC gate. Registered in the HTTP kernel as 'permission' and
 * used as: ->middleware('permission:create_revenue').
 *
 * This is the server-side enforcement point referenced throughout the
 * brief (Sections 32-34): the existing frontend already hides buttons a
 * role should not see (js/auth.js canAccessModule()), but that is purely
 * cosmetic. Every state-changing route additionally passes through this
 * middleware so a request forged outside the UI (curl, browser console,
 * modified JS) is rejected with 403 regardless of what the client sends.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        if (! $user->isActive()) {
            abort(403, 'This account is not active.');
        }

        foreach ($permissions as $permission) {
            if (! $user->can($permission)) {
                abort(403, 'You do not have permission to perform this action.');
            }
        }

        return $next($request);
    }
}
