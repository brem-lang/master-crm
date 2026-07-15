<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CompanyUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('companies')->name('companies.')->group(function () {
    Route::get('/', [CompanyController::class, 'index'])->name('index');
    Route::post('/', [CompanyController::class, 'store'])->name('store');
    Route::delete('/bulk-destroy', [CompanyController::class, 'bulkDestroy'])->name('bulk-destroy');
    Route::post('/{company}/users', [CompanyUserController::class, 'store'])->name('users.store');
    Route::delete('/{company}/users/{user}', [CompanyUserController::class, 'destroy'])->name('users.destroy');
    Route::put('/{company}', [CompanyController::class, 'update'])->name('update');
    Route::delete('/{company}', [CompanyController::class, 'destroy'])->name('destroy');
});
