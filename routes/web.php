<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| The existing frontend (index.html, /pages/*.html, /css, /js) is served as
| plain static files from public/app/ -- see docs/INSTALLATION.md, step 4.
| Being static, they are never routed through this file directly; Apache/
| Nginx (or `php artisan serve`, which also serves public/ as static root)
| hands them back without touching Laravel at all. This file only owns the
| handful of routes that need real server-side logic: authentication,
| password management, and the "/" convenience redirect.
|
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect('/app/pages/dashboard.html')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/forgot-password', function () {
        return view('auth.forgot-password');
    })->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'requestLink'])->name('password.email');

    Route::get('/reset-password/{token}', function (string $token) {
        return view('auth.reset-password', ['token' => $token, 'email' => request('email')]);
    })->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/password', [PasswordController::class, 'update'])->name('password.change');

    Route::get('/dashboard', function () {
        // Convenience alias so a bookmarked "/dashboard" (a very natural
        // guess) reaches the real static dashboard instead of a 404.
        return redirect('/app/pages/dashboard.html');
    })->name('dashboard');
});
