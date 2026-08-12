<?php

use App\Http\Controllers\AffiliateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
    Route::patch('/affiliates/{affiliate}/status', [AffiliateController::class, 'updateStatus'])->name('affiliates.update-status');
    Route::patch('/affiliates/bulk-status', [AffiliateController::class, 'bulkUpdateStatus'])->name('affiliates.bulk-update-status');
    Route::delete('/affiliates/bulk-destroy', [AffiliateController::class, 'bulkDestroy'])->name('affiliates.bulk-destroy');
    Route::delete('/affiliates/{affiliate}', [AffiliateController::class, 'destroy'])->name('affiliates.destroy');
});
