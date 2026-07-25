<?php

use App\Http\Controllers\AdvertiserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/advertisers', [AdvertiserController::class, 'index'])->name('advertisers.index');
    Route::post('/advertisers/{advertiser}/send-test-lead', [AdvertiserController::class, 'sendTestLead'])->name('advertisers.send-test-lead');
    Route::delete('/advertisers/bulk-destroy', [AdvertiserController::class, 'bulkDestroy'])->name('advertisers.bulk-destroy');
    Route::delete('/advertisers/{advertiser}', [AdvertiserController::class, 'destroy'])->name('advertisers.destroy');
});
