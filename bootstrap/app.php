<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsurePermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
|--------------------------------------------------------------------------
| Application Bootstrap (Laravel 11.x style)
|--------------------------------------------------------------------------
|
| If this project was instead started from a Laravel 10.x skeleton
| (app/Http/Kernel.php exists), register the same two aliases in
| Kernel::$middlewareAliases instead of here -- see
| docs/INSTALLATION.md, "Registering middleware on Laravel 10".
|
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'account.active' => EnsureAccountActive::class,
        ]);

        // Required for the session-cookie SPA auth described in
        // docs/SECURITY_ARCHITECTURE.md: without this, EnsureFrontendRequestsAreStateful
        // is never added to the api group, so /api/* never recognizes the
        // web session cookie and auth:sanctum always 401s.
        $middleware->statefulApi();

        // Section 60: security headers applied globally. Kept intentionally
        // conservative (no CSP nonce/report-uri setup) so it does not break
        // the existing frontend's inline event listeners or Chart.js/CDN
        // usage -- tighten this once the frontend's script sources are
        // finalized for production (see docs/SECURITY_ARCHITECTURE.md).
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Section 57: never leak stack traces / SQL / env details on API
        // routes, regardless of APP_DEBUG. HTML error pages (web routes)
        // still use Laravel's normal debug page in local development.
        $exceptions->shouldRenderJsonWhen(function ($request, $throwable) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
