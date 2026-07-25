<?php

use App\Http\Controllers\AffiliateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
    Route::delete('/affiliates/bulk-destroy', [AffiliateController::class, 'bulkDestroy'])->name('affiliates.bulk-destroy');
    Route::delete('/affiliates/{affiliate}', [AffiliateController::class, 'destroy'])->name('affiliates.destroy');
});
