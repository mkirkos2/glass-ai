<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')
    ->middleware('auth')
    ->name('home');

Route::middleware('auth')->group(function () {
    Route::post('/chat', [ChatController::class, 'send'])
        ->middleware('throttle:10,1')
        ->name('chat.send');

    Route::get('/chat/conversations', [ChatController::class, 'index'])
        ->name('chat.conversations.index');

    Route::get('/chat/conversations/{id}', [ChatController::class, 'show'])
        ->name('chat.conversations.show');

    Route::delete('/chat/conversations/{id}', [ChatController::class, 'destroy'])
        ->name('chat.conversations.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
