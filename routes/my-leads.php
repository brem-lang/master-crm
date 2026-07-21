<?php

use App\Http\Controllers\MyLeadsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->get('/my-leads', [MyLeadsController::class, 'index'])->name('my-leads.index');
