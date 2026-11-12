<?php

use App\Http\Controllers\LeadLogsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->get('/leads-logs', [LeadLogsController::class, 'index'])->name('leads-logs.index');
