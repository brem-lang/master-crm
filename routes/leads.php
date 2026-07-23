<?php

use App\Http\Controllers\LeadsController;
use App\Http\Controllers\SalesRepLeaderboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/leads', [LeadsController::class, 'index'])->name('leads.index');
    Route::get('/leads/rejected', [LeadsController::class, 'rejected'])->name('leads.rejected');
    Route::get('/leads/conversions', [LeadsController::class, 'conversions'])->name('leads.conversions');
    Route::patch('/leads/{lead}/assign', [LeadsController::class, 'assign'])->name('leads.assign');
    Route::patch('/leads/{lead}/release-ftd', [LeadsController::class, 'releaseFtd'])->name('leads.release-ftd');
    Route::patch('/leads/bulk-assign', [LeadsController::class, 'bulkAssign'])->name('leads.bulk-assign');
    Route::get('/leads/leaderboard', [SalesRepLeaderboardController::class, 'index'])->name('leads.leaderboard');
});
