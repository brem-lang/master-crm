<?php

use App\Http\Controllers\LogViewerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->get('/logs', [LogViewerController::class, 'index'])->name('logs.index');
