<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Middleware\UserActiveMiddleware;
use App\Http\Controllers\BankController;


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

});

// Bank management routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('banks', BankController::class);
}); 

require __DIR__ . '/auth.php';
require __DIR__ . '/settings.php'; 
require __DIR__ . '/miniwallet.php';
