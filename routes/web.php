<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Middleware\UserActiveMiddleware;


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

require __DIR__ . '/auth.php';
require __DIR__ . '/settings.php'; 
require __DIR__ . '/miniwallet.php';
