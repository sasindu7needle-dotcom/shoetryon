<?php

use App\Http\Controllers\TryOnController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TryOnController::class, 'home'])->name('home');
Route::get('/tryon', [TryOnController::class, 'show'])->name('tryon');
