<?php

use App\Http\Controllers\GlobalSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->get('/search', [GlobalSearchController::class, 'index'])->name('search.index');
