<?php

use App\Http\Controllers\OpenAIController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::get('/chat', [OpenAIController::class, 'index'])->name('welcome');
Route::post('/chat/send', [OpenAIController::class, 'test'])->name('welcome.send');
Route::post('/test', [OpenAIController::class, 'test'])->withoutMiddleware('auth');

use App\Http\Controllers\Admin\RagDocumentController;

Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::post('/rag/documents', [RagDocumentController::class, 'store'])
            ->name('rag.documents.store');

        Route::get('/rag/documents/{document}', [RagDocumentController::class, 'show'])
            ->name('rag.documents.show');
    });


Route::get('/chrono-test', function () {
    return view('chrono-test');
})->middleware('auth');

Route::middleware('auth')->group(function () {
    // Session Management
    Route::get('/chat/sessions', [ChatController::class, 'indexSessions'])->name('chat.sessions.index');
    Route::post('/chat/sessions', [ChatController::class, 'storeSession'])->name('chat.sessions.store');

    // Chat Interaction
    Route::get('/chat/sessions/{session}', [ChatController::class, 'showMessages'])->name('chat.sessions.show');
    Route::post('/chat/sessions/{session}/send', [ChatController::class, 'sendMessage'])->name('chat.sessions.send');
});

Route::middleware(['auth'])->group(function () {
    // Note: The URL prefix is still /api/... for consistency with your JS, 
    // but they live in web.php so they get Session/Cookie support.

    Route::prefix('api/chat')->name('chat.')->group(function () {
        Route::get('/sessions', [ChatController::class, 'indexSessions'])->name('sessions.index');
        Route::post('/sessions', [ChatController::class, 'storeSession'])->name('sessions.store');

        // Use model binding for {session} if possible, otherwise {sessionId}
        Route::get('/sessions/{session}', [ChatController::class, 'showMessages'])->name('sessions.show');
        Route::post('/sessions/{session}/send', [ChatController::class, 'sendMessage'])->name('sessions.send');
    });

    Route::prefix('api/rag')->name('rag.')->group(function () {
        Route::post('/documents', [RagDocumentController::class, 'store'])->name('documents.store');
        Route::get('/documents', [RagDocumentController::class, 'index'])->name('documents.index');
    });
});
require __DIR__ . '/auth.php';
