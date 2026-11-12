<?php

use App\Http\Controllers\Api\LeadLogController;
use App\Http\Middleware\AuthenticateLeadLogsToken;
use Illuminate\Support\Facades\Route;

Route::middleware([AuthenticateLeadLogsToken::class, 'throttle:120,1'])
    ->post('/leads-logs', [LeadLogController::class, 'store'])
    ->name('api.leads-logs.store');
