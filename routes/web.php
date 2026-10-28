<?php

use App\Http\Controllers\OpenAIController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/chat', [OpenAIController::class, 'index'])->name('welcome');
Route::post('/chat/send', [OpenAIController::class, 'test'])->name('welcome.send');
Route::post('/test', [OpenAIController::class, 'test'])->withoutMiddleware('auth');
require __DIR__ . '/auth.php';
