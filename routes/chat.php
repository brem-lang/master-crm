<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('companies/{company}/chat')->name('chat.')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('index');
    Route::get('/messages', [ChatController::class, 'messages'])->name('messages');
    Route::post('/messages', [ChatController::class, 'store'])->middleware('throttle:30,1')->name('store');
});
