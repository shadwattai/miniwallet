<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\{Route, DB};
use App\Http\Middleware\UserActiveMiddleware;

use App\Http\Controllers\UsersController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\WalletsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TransactionsController;

Route::middleware(
    [
        'auth',
        'verified',
        UserActiveMiddleware::class,
    ]
)->prefix('/miniwallet')->group(function () {

    Route::get('/wallets', [
        WalletsController::class,
        'getWallets'
    ])->name('wallets');

    Route::get('/transactions', [
        TransactionsController::class,
        'getTransactions'
    ])->name('transactions');

    Route::get('/settings', [
        SettingsController::class,
        'index'
    ])->name('settings.index');

    Route::post('/settings/users', [
        UsersController::class,
        'store'
    ])->name('settings.users.create');

    // Audit routes (under settings)
    Route::get('/settings/audit', [AuditController::class, 'index'])->name('settings.audit.index');
});
