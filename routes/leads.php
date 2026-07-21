<?php

use App\Http\Controllers\LeadsController;
use App\Http\Controllers\SalesRepLeaderboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/leads', [LeadsController::class, 'index'])->name('leads.index');
    Route::patch('/leads/{lead}/assign', [LeadsController::class, 'assign'])->name('leads.assign');
    Route::patch('/leads/bulk-assign', [LeadsController::class, 'bulkAssign'])->name('leads.bulk-assign');
    Route::get('/leads/leaderboard', [SalesRepLeaderboardController::class, 'index'])->name('leads.leaderboard');
});
