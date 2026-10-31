<?php

namespace App\Services;

use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Services\Gemini\GeminiService;
use App\Services\Rag\RagRetrievalService;
use Throwable;

class ChatService
{
    public function __construct(
        protected GeminiService $gemini,
        protected RagRetrievalService $rag,
    ) {}

    /**
     * Process a single turn in the chat.
     */
    public function sendMessage(
        ChatSession $session,
        string $prompt,
        ?int $userId = null
    ): ChatMessage {

        // 1. Save User Message immediately so it appears in UI even if AI fails
        $userMessage = ChatMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $prompt,
        ]);

        try {
            // 2. Retrieve Context from RAG (Qdrant)
            // We pass the session's user to apply visibility filters
            $context = $this->rag->retrieveContext($prompt, $session->user);

            // 3. Build History Array for Gemini
            // Exclude the message we just created to avoid duplication in the input steps
            $historyMessages = $session->messages()
                ->where('id', '<', $userMessage->id)
                ->get();

            $historyArray = $historyMessages->map(function ($msg) {
                return [
                    'role' => $msg->role === 'assistant' ? 'model' : 'user',
                    'text' => $msg->content,
                ];
            })->toArray();

            // 4. Call Gemini Service
            $reply = $this->gemini->generate(
                prompt: $prompt,
                history: $historyArray,
                context: $context
            );

            // 5. Save Assistant Message
            $assistantMessage = ChatMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $reply,
                // Store debug info about what RAG found
                'retrieved_chunks_meta' => $context ? ['has_context' => true] : ['has_context' => false],
            ]);

            return $assistantMessage;
        } catch (Throwable $e) {
            // Log error but re-throw to let Controller handle response
            logger()->error('ChatService Error', [
                'session_id' => $session->id,
                'message' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get full history for a session.
     */
    public function getHistory(ChatSession $session): array
    {
        return $session->messages()->get()->map(fn($msg) => [
            'id' => $msg->id,
            'role' => $msg->role,
            'content' => $msg->content,
            'created_at' => $msg->created_at->toIso8601String(),
        ])->toArray();
    }
}
