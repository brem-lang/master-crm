<?php

use App\Http\Controllers\DistributionRulesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/distribution-rules', [DistributionRulesController::class, 'index'])->name('distribution-rules.index');
});
