<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_session_and_send_message()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1. Create Session
        $response = $this->postJson('/chat/sessions', [
            'title' => 'Water Cycle Lesson',
            'subject' => 'Science',
        ]);

        $response->assertStatus(201);
        $sessionId = $response->json('id');

        // 2. Mock External APIs (Gemini & Qdrant)
        Http::fake([
            // Embedding Call (for RAG retrieval)
            'generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:batchEmbedContents' => Http::response([
                'embeddings' => [['values' => array_fill(0, 3072, 0.1)]],
            ], 200),

            // Qdrant Search (returns empty result for simplicity in this test)
            '127.0.0.1:6333/collections/*/points/search' => Http::response([
                'result' => [],
            ], 200),

            // Gemini Generate (The Critical Part)
            // Ensure this EXACTLY matches what extractResponseText() looks for
            'https://generativelanguage.googleapis.com/v1beta/interactions' => Http::response([
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Hello from AI!' // Must be here
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        // 3. Send Message
        $response = $this->postJson("/chat/sessions/{$sessionId}/send", [
            'prompt' => 'Hi there!',
        ]);

        // Debug: If it fails, dump the response body to see the error message
        if ($response->status() !== 200) {
            dd($response->json());
        }

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message.content', 'Hello from AI!');

        // 4. Verify Persistence
        $this->assertDatabaseHas('chrono_chat_messages', [
            'session_id' => $sessionId,
            'role' => 'user',
            'content' => 'Hi there!',
        ]);

        $this->assertDatabaseHas('chrono_chat_messages', [
            'session_id' => $sessionId,
            'role' => 'assistant',
            'content' => 'Hello from AI!',
        ]);
    }
}
