<?php

use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users/validate', [UserController::class, 'validateUser']);
    Route::post('/users/validate-amount', [UserController::class, 'validateAmount']);
});

