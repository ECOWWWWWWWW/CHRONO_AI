<?php

namespace Tests\Feature;

use App\Models\RagDocument;
use App\Services\Rag\RagDocumentService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RagIngestionTest extends TestCase
{

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('qdrant.host', '127.0.0.1');
        Config::set('qdrant.port', 6333);
        Config::set('qdrant.collection', 'test_chrona_rag');
        Config::set('qdrant.api_key', null);

        Config::set('rag.embedding.dimension', 768);
        Config::set('rag.embedding.batch_size', 50);

        Config::set('rag.chunk.max_chars', 300);
        Config::set('rag.chunk.overlap', 50);
        Config::set('rag.chunk.min_chars', 50);
    }

    public function test_it_indexes_text_document_into_qdrant(): void
    {
        Storage::fake('local');

        Http::fake([
            'generativelanguage.googleapis.com/*' => function ($request) {
                $payload = json_decode($request->body(), true) ?? [];
                $count = count($payload['requests'] ?? []);

                $embeddings = [];

                for ($i = 0; $i < $count; $i++) {
                    $embeddings[] = [
                        'values' => array_fill(0, 768, 0.123),
                    ];
                }

                return Http::response([
                    'embeddings' => $embeddings,
                ], 200);
            },

            '127.0.0.1:6333/*' => Http::response([
                'result' => true,
                'status' => 'ok',
                'time' => 0.01,
            ], 200),
        ]);

        $service = app(RagDocumentService::class);

        $document = $service->createFromText(
            title: 'Grade 7 Science Chapter 4',
            text: str_repeat('The water cycle includes evaporation, condensation, precipitation, and collection. ', 30),
            sourceType: RagDocument::SOURCE_ADMIN_CURRICULUM,
            metadata: [
                'subject' => 'Science',
                'grade' => '7',
                'chapter' => '4',
                'visibility' => 'global',
            ],
        );

        $service->index($document);

        $document->refresh();

        $this->assertSame(RagDocument::STATUS_INDEXED, $document->status);
        $this->assertGreaterThan(0, $document->chunks()->count());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'batchEmbedContents');
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/collections/test_chrona_rag/points');
        });
    }

    public function test_failed_document_is_marked_failed_when_extraction_fails(): void
    {
        Storage::fake('local');

        Http::fake();

        $service = app(RagDocumentService::class);

        $document = $service->createFromText(
            title: 'Empty Document',
            text: '',
            sourceType: RagDocument::SOURCE_ADMIN_CURRICULUM,
        );

        try {
            $service->index($document);
        } catch (\Throwable $e) {
            // expected
        }

        $document->refresh();

        $this->assertSame(RagDocument::STATUS_FAILED, $document->status);
        $this->assertNotNull($document->error_message);
    }
}
