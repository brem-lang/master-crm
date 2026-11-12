<?php

use App\Http\Controllers\LeadLogsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/leads-logs', [LeadLogsController::class, 'index'])->name('leads-logs.index');
    Route::delete('/leads-logs/bulk-destroy', [LeadLogsController::class, 'bulkDestroy'])->name('leads-logs.bulk-destroy');
});
