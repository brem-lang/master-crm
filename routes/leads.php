<?php

use App\Http\Controllers\LeadsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->get('/leads', [LeadsController::class, 'index'])->name('leads.index');
