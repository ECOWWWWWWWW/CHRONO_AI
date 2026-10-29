<?php

namespace Tests\Feature;

use App\Models\RagDocument;
use App\Models\RagChunk;
use App\Models\User;
use App\Services\Rag\RagRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RagRetrievalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('qdrant.host', '127.0.0.1');
        Config::set('qdrant.port', 6333);
        Config::set('qdrant.collection', 'test_chrono_rag');

        Config::set('rag.embedding.model', 'gemini-embedding-001');
        Config::set('rag.embedding.dimension', 3072);

        Config::set('rag.retrieval.limit', 5);
        Config::set('rag.retrieval.score_threshold', 0.5);
    }

    public function test_it_retrieves_context_for_global_documents()
    {
        // Mock Embedding Response
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embeddings' => [
                    ['values' => array_fill(0, 3072, 0.1)],
                ],
            ], 200),
        ]);

        // Mock Qdrant Search Response
        Http::fake([
            '127.0.0.1:6333/collections/test_chrono_rag/points/search' => Http::response([
                'result' => [
                    [
                        'id' => 'uuid-1',
                        'score' => 0.95,
                        'payload' => [
                            'text' => 'Chapter 4 covers the water cycle.',
                            'title' => 'Science Grade 7',
                            'source_type' => 'admin_curriculum',
                            'visibility' => 'global',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(RagRetrievalService::class);
        $user = User::factory()->create(['id' => 1]);

        $context = $service->retrieveContext('Tell me about chapter 4', $user);

        $this->assertNotNull($context);
        $this->assertStringContainsString('Chapter 4 covers the water cycle', $context);
        $this->assertStringContainsString('[Source 1: Science Grade 7 (ADMIN_CURRICULUM)]', $context);
    }

    public function test_it_filters_out_low_score_results()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embeddings' => [['values' => array_fill(0, 3072, 0.1)]],
            ], 200),
            '127.0.0.1:6333/collections/test_chrono_rag/points/search' => Http::response([
                'result' => [
                    [
                        'id' => 'uuid-1',
                        'score' => 0.4, // Below threshold of 0.5
                        'payload' => ['text' => 'Irrelevant info', 'visibility' => 'global'],
                    ],
                ],
            ], 200),
        ]);

        $service = app(RagRetrievalService::class);
        $context = $service->retrieveContext('irrelevant query', null);

        $this->assertNull($context);
    }

    public function test_controller_injects_context_into_gemini_call()
    {
        // Setup DB with a dummy document/chunk so ingestion logic doesn't fail if triggered elsewhere
        // But primarily we are testing the CONTROLLER flow

        Http::fake([
            // 1. Embedding Call (from RagRetrievalService)
            'generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:batchEmbedContents' => Http::response([
                'embeddings' => [['values' => array_fill(0, 3072, 0.1)]],
            ], 200),

            // 2. Qdrant Search Call (from RagRetrievalService)
            '127.0.0.1:6333/collections/test_chrono_rag/points/search' => Http::response([
                'result' => [
                    [
                        'id' => 'uuid-1',
                        'score' => 0.9,
                        'payload' => [
                            'text' => 'RETRIEVED_CONTEXT_TEXT',
                            'title' => 'Test Doc',
                            'source_type' => 'admin_curriculum',
                            'visibility' => 'global',
                        ],
                    ],
                ],
            ], 200),

            // 3. Gemini Chat Completion Call (from GeminiService)
            'https://generativelanguage.googleapis.com/v1beta/interactions' => Http::response([
                'steps' => [
                    [
                        'type' => 'model_output',
                        'content' => [['type' => 'text', 'text' => 'Final Answer']],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/test', [
            'prompt' => 'What is chapter 4?',
            'history' => [],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('reply', 'Final Answer')
            ->assertJsonPath('used_rag', true);

        // Assert that the Gemini request contained the retrieved context
        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), 'interactions')) {
                return true; // Skip non-Gemini calls
            }

            $body = $request->data();
            $systemInstruction = $body['system_instruction'] ?? '';

            return str_contains($systemInstruction, '[RETRIEVED CURRICULUM CONTEXT]')
                && str_contains($systemInstruction, 'RETRIEVED_CONTEXT_TEXT');
        });
    }
}
