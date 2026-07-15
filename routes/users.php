<?php

use App\Http\Controllers\Admin\ImpersonateController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::delete('/impersonate', [ImpersonateController::class, 'stop'])->name('impersonate.stop');
    Route::delete('/bulk-destroy', [UserController::class, 'bulkDestroy'])->name('bulk-destroy');

    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    Route::post('/{user}/impersonate', [ImpersonateController::class, 'start'])->name('impersonate.start');
});
