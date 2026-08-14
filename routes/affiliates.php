<?php

use App\Http\Controllers\AffiliateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/affiliates', [AffiliateController::class, 'index'])->name('affiliates.index');
    // Hidden: not linked from the sidebar and disabled at the controller level (see whitelistedIps()).
    Route::get('/affiliates/whitelisted-ips', [AffiliateController::class, 'whitelistedIps'])->name('affiliates.whitelisted-ips');
    Route::patch('/affiliates/{affiliate}/status', [AffiliateController::class, 'updateStatus'])->name('affiliates.update-status');
    Route::patch('/affiliates/bulk-status', [AffiliateController::class, 'bulkUpdateStatus'])->name('affiliates.bulk-update-status');
    Route::patch('/affiliates/bulk-update', [AffiliateController::class, 'bulkUpdate'])->name('affiliates.bulk-update');
    Route::delete('/affiliates/bulk-destroy', [AffiliateController::class, 'bulkDestroy'])->name('affiliates.bulk-destroy');
    Route::patch('/affiliates/{affiliate}', [AffiliateController::class, 'update'])->name('affiliates.update');
    Route::delete('/affiliates/{affiliate}', [AffiliateController::class, 'destroy'])->name('affiliates.destroy');
});
