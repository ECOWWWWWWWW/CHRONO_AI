<?php

use App\Http\Controllers\OpenAIController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

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
require __DIR__ . '/auth.php';
