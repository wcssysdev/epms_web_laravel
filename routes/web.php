<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ConfigController;

// ──────────────────────────────────────────────────────────────────────────────
// PUBLIC — Auth routes (no auth required)
// ──────────────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login',[LoginController::class, 'login'])->name('login.post');
    Route::get('/',      fn() => redirect()->route('login'));
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth.check')
    ->name('logout');

// ──────────────────────────────────────────────────────────────────────────────
// PROTECTED — All authenticated routes
// ──────────────────────────────────────────────────────────────────────────────
Route::middleware(['auth.check'])->group(function () {

    // Change Password (accessible to all roles)
    Route::get('/change-password',  [ChangePasswordController::class, 'index'])->name('change-password');
    Route::post('/change-password', [ChangePasswordController::class, 'update'])->name('change-password.post');

    // ── Dashboard ──────────────────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/home',      [DashboardController::class, 'index'])->name('home');

    // ── Admin routes (Company Admin level 30 and above) ────────────────────
    Route::middleware(['role:30'])->prefix('admin')->name('admin.')->group(function () {

        // User Management
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/',                         [UserController::class, 'index'])->name('index');
            Route::get('/datatable',                [UserController::class, 'getDatatable'])->name('datatable');
            Route::get('/create',                   [UserController::class, 'create'])->name('create');
            Route::post('/',                        [UserController::class, 'store'])->name('store');
            Route::get('/{user}/edit',              [UserController::class, 'edit'])->name('edit');
            Route::put('/{user}',                   [UserController::class, 'update'])->name('update');
            Route::post('/{user}/reset-password',   [UserController::class, 'resetPassword'])->name('reset-password');
            Route::post('/{user}/toggle-active',    [UserController::class, 'toggleActive'])->name('toggle-active');
            Route::post('/{user}/reset-session',    [UserController::class, 'resetSession'])->name('reset-session');
        });

        // Estate Settings / Config
        Route::prefix('config')->name('config.')->group(function () {
            Route::get('/',             [ConfigController::class, 'index'])->name('index');
            Route::put('/',             [ConfigController::class, 'update'])->name('update');
            Route::post('/test-sap',    [ConfigController::class, 'testSapConnection'])->name('test-sap');
            Route::post('/toggle-lock', [ConfigController::class, 'toggleSystemLock'])->name('toggle-lock');
        });

    });

    // ── Masters routes (Estate Manager level 40 and above) ────────────────
    Route::middleware(['role:40'])->prefix('masters')->name('masters.')->group(function () {
        // TODO Sprint 2+: Estate, Division, Block, Employee, etc.
    });

    // ── Grouping routes ────────────────────────────────────────────────────
    Route::middleware(['role:50'])->prefix('grouping')->name('grouping.')->group(function () {
        // TODO Sprint 5: Gang Employee, Field Staff, Mandor, Asst Manager
    });

    // ── Planning routes ────────────────────────────────────────────────────
    Route::middleware(['role:60'])->prefix('planning')->name('planning.')->group(function () {
        // TODO Sprint 6+
    });

    // ── Transactions routes ────────────────────────────────────────────────
    Route::middleware(['role:70'])->prefix('transactions')->name('transactions.')->group(function () {
        // TODO Sprint 6+
    });

    // ── Reporting routes ───────────────────────────────────────────────────
    Route::prefix('reporting')->name('reporting.')->group(function () {
        // TODO Sprint 7+
    });

});

// ──────────────────────────────────────────────────────────────────────────────
// SUPER ADMIN routes
// ──────────────────────────────────────────────────────────────────────────────
Route::middleware(['auth.check', 'role:10'])->prefix('super-admin')->name('super-admin.')->group(function () {
    // TODO Sprint 1: Country, Company management
});

// ──────────────────────────────────────────────────────────────────────────────
// COUNTRY ADMIN routes
// ──────────────────────────────────────────────────────────────────────────────
Route::middleware(['auth.check', 'role:20'])->prefix('country-admin')->name('country-admin.')->group(function () {
    // TODO Sprint 1: Cross-company views
});
