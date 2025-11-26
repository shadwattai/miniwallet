<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\{Route, DB};
use App\Http\Middleware\UserActiveMiddleware;

use App\Http\Controllers\BankController;
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

    // Wallet creation route (must come before general /wallets route)
    Route::get('/wallets/create', [
        WalletsController::class,
        'create'
    ])->name('wallets.create');

    Route::get('/wallets', [
        WalletsController::class,
        'getWallets'
    ])->name('wallets');

    Route::post('/wallets', [
        WalletsController::class,
        'store'
    ])->name('wallets.store');

    Route::get('/mywallets', [
        WalletsController::class,
        'getMyWallets'
    ])->name('mywallets');

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
    
    // User management routes
    Route::patch('/users/{key}', [
        UsersController::class,
        'update'
    ])->name('users.update');
    
    Route::get('/users/{key}', [
        UsersController::class,
        'show'
    ])->name('users.show');
    
    Route::delete('/users/{key}', [
        UsersController::class,
        'destroy'
    ])->name('users.destroy');
    
    Route::get('/users/search', [
        UsersController::class,
        'search'
    ])->name('users.search');
    
    Route::get('/users/stats', [
        UsersController::class,
        'getStats'
    ])->name('users.stats');

    // Audit routes (under settings)
    Route::get('/settings/audit', [AuditController::class, 'index'])->name('settings.audit.index');


    // Audit routes (under settings)
    Route::get('/banks', [BankController::class, 'getBanks'])->name('settings.banks.index');
    
    // Banks API for wallet creation
    Route::get('/wallets/banks', [BankController::class, 'getBanksForWallets'])->name('wallets.banks');

    Route::post('/wallet', [WalletsController::class, 'createWallet'])->name('wallets.create');
});
