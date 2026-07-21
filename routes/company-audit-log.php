<?php

use App\Http\Controllers\CompanyAuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->get('/company/audit-log', [CompanyAuditLogController::class, 'index'])->name('company-audit-log.index');
