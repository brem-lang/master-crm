<?php

use App\Http\Controllers\DistributionRulesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/distribution-rules', [DistributionRulesController::class, 'index'])->name('distribution-rules.index');
    Route::get('/distribution-rules/bulk-edit-options', [DistributionRulesController::class, 'bulkEditOptions'])->name('distribution-rules.bulk-edit-options');
    Route::patch('/distribution-rules/bulk-update', [DistributionRulesController::class, 'bulkUpdate'])->name('distribution-rules.bulk-update');
    Route::get('/distribution-rules/{distributionRule}/edit-options', [DistributionRulesController::class, 'editOptions'])->name('distribution-rules.edit-options');
    Route::patch('/distribution-rules/{distributionRule}', [DistributionRulesController::class, 'update'])->name('distribution-rules.update');
});
