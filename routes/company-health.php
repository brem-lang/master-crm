<?php

use App\Http\Controllers\CompanyHealthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('company')->name('company-health.')->group(function () {
    Route::get('/health', [CompanyHealthController::class, 'index'])->name('index');
    Route::post('/health/pull-data', [CompanyHealthController::class, 'pullData'])->name('pull-data');
});
