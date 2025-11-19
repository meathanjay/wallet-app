<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('wallet');
    }
    return redirect('/login');
})->name('home');

Route::get('dashboard', function () {
    return redirect()->route('wallet');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('wallet', function () {
    return Inertia::render('Wallet');
})->middleware(['auth', 'verified'])->name('wallet');

Route::get('contacts', function () {
    return Inertia::render('Contacts');
})->middleware(['auth', 'verified'])->name('contacts');

Route::get('transactions', function () {
    return Inertia::render('Transactions');
})->middleware(['auth', 'verified'])->name('transactions');

require __DIR__.'/settings.php';
