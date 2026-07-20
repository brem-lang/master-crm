<?php

use App\Http\Controllers\JobsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->get('/jobs', [JobsController::class, 'index'])->name('jobs.index');
