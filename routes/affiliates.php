<?php

use App\Http\Controllers\AffiliateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->get('/affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
