<?php

use App\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
