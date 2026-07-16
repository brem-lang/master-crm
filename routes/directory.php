<?php

use App\Http\Controllers\CompanyDirectoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->get('/directory', [CompanyDirectoryController::class, 'index'])->name('directory.index');
