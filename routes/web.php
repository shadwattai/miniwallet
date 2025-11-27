<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Middleware\UserActiveMiddleware;
use App\Http\Controllers\BankController;
use App\Http\Controllers\TransactionsController;


Route::middleware(
    [
        'auth',
        'verified',
        UserActiveMiddleware::class,
    ]
)
->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');

    Route::resource('banks', BankController::class);
}); 

Route::middleware(
    [
        'auth',
        'verified',
        UserActiveMiddleware::class,
    ]
)->prefix('/api')->group(function () {
    Route::post('/transactions', [TransactionsController::class, 'transferMoney'])->name('wallets.transfer');
    Route::get('/transactions', [TransactionsController::class, 'getTransactions'])->name('wallets.transactions');
    Route::get('/search-wallets', [TransactionsController::class, 'searchWallets'])->name('wallets.search');
});

require __DIR__ . '/auth.php';
require __DIR__ . '/settings.php'; 
require __DIR__ . '/miniwallet.php';
