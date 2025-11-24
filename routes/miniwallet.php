<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\{Route, DB};

use App\Http\Controllers\HomeController;
use App\Http\Middleware\UserActiveMiddleware;

use App\Http\Controllers\WalletsController;


Route::middleware(
    [
        'auth',
        'verified',
        UserActiveMiddleware::class,
    ]
)->prefix('/miniwallet')->group(function () {
    
    Route::get('/wallets', [
        WalletsController::class, 'getWallets'
    ])->name('wallets');
    
    Route::get('/transactions', [
        WalletsController::class, 'getTransactions'
    ])->name('transactions');

    Route::get('/settings', [
        WalletsController::class, 'getSettings'
    ])->name('settings');

});
