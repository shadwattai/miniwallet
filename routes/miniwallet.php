<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\{Route, DB}; 
use App\Http\Middleware\UserActiveMiddleware;

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
        WalletsController::class, 'getWallets'
    ])->name('wallets');
    
    Route::get('/transactions', [
        TransactionsController::class, 'getTransactions'
    ])->name('transactions');

    Route::get('/settings', [
        SettingsController::class, 'getSettings'
    ])->name('settings');

});
