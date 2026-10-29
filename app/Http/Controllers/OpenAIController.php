<?php

namespace App\Http\Controllers;

use App\Exceptions\Gemini\GeminiException;
use App\Services\Gemini\GeminiService;
use App\Services\Rag\RagRetrievalService; // NEW IMPORT
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAIController extends Controller
{
    public function index()
    {
        return view('welcome');
    }

    public function test(Request $request, GeminiService $gemini, RagRetrievalService $rag) // INJECT RAG SERVICE
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:50000',
            'history' => 'nullable|array',
            'history.*.role' => 'required|string|in:user,model',
            'history.*.text' => 'required|string',
        ]);

        try {
            // 1. RETRIEVE CONTEXT FROM RAG
            $context = $rag->retrieveContext(
                prompt: $validated['prompt'],
                user: $request->user() // Pass authenticated user for filtering
            );

            // 2. GENERATE RESPONSE WITH CONTEXT
            $reply = $gemini->generate(
                prompt: $validated['prompt'],
                history: $validated['history'] ?? [],
                context: $context // Inject retrieved curriculum data
            );

            return response()->json([
                'success' => true,
                'reply' => $reply,
                'used_rag' => filled($context), // Debug flag
            ]);
        } catch (GeminiException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'exception' => class_basename($e),
                'details' => $e->details(),
            ], $e->status());
        } catch (Throwable $e) {
            Log::error('Unexpected error in OpenAIController@test', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred.',
            ], 500);
        }
    }
}
