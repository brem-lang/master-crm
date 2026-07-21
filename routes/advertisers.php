<?php

use App\Http\Controllers\AdvertiserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->get('/advertisers', [AdvertiserController::class, 'index'])->name('advertisers.index');
