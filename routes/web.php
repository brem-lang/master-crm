<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/users.php';
require __DIR__.'/roles.php';
require __DIR__.'/companies.php';
require __DIR__.'/directory.php';
require __DIR__.'/leads.php';
require __DIR__.'/jobs.php';
require __DIR__.'/audit-log.php';
require __DIR__.'/notifications.php';
require __DIR__.'/search.php';
require __DIR__.'/company-health.php';
require __DIR__.'/company-audit-log.php';
require __DIR__.'/my-leads.php';
require __DIR__.'/affiliates.php';
require __DIR__.'/advertisers.php';
require __DIR__.'/logs.php';
