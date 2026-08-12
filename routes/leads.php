<?php

use App\Http\Controllers\LeadsController;
use App\Http\Controllers\SalesRepLeaderboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/leads', [LeadsController::class, 'index'])->name('leads.index');
    Route::get('/leads/export', [LeadsController::class, 'export'])->name('leads.export');
    Route::get('/leads/rejected', [LeadsController::class, 'rejected'])->name('leads.rejected');
    Route::get('/leads/conversions', [LeadsController::class, 'conversions'])->name('leads.conversions');
    Route::patch('/leads/{lead}/assign', [LeadsController::class, 'assign'])->name('leads.assign');
    Route::patch('/leads/{lead}/release-ftd', [LeadsController::class, 'releaseFtd'])->name('leads.release-ftd');
    Route::patch('/leads/conversions/bulk-release-ftd', [LeadsController::class, 'bulkReleaseFtd'])->name('leads.conversions.bulk-release-ftd');
    Route::get('/leads/{lead}/resend-options', [LeadsController::class, 'resendOptions'])->name('leads.resend-options');
    Route::patch('/leads/{lead}/resend', [LeadsController::class, 'resend'])->name('leads.resend');
    Route::patch('/leads/bulk-assign', [LeadsController::class, 'bulkAssign'])->name('leads.bulk-assign');
    Route::patch('/leads/column-preferences', [LeadsController::class, 'updateColumnPreferences'])->name('leads.column-preferences.update');
    Route::delete('/leads/bulk-destroy', [LeadsController::class, 'bulkDestroy'])->name('leads.bulk-destroy');
    Route::delete('/leads/rejected/bulk-destroy', [LeadsController::class, 'bulkDestroyRejected'])->name('leads.rejected.bulk-destroy');
    Route::delete('/leads/rejected/{lead}', [LeadsController::class, 'destroyRejected'])->name('leads.rejected.destroy');
    Route::delete('/leads/{lead}', [LeadsController::class, 'destroy'])->name('leads.destroy');
    Route::get('/leads/leaderboard', [SalesRepLeaderboardController::class, 'index'])->name('leads.leaderboard');
});
