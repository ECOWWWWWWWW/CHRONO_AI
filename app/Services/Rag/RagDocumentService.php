<?php

namespace App\Services\Rag;

use App\Models\RagDocument;
use App\Services\Rag\Contracts\EmbeddingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

class RagDocumentService
{
    public function __construct(
        protected TextExtractionService $extractor,
        protected ChunkTextService $chunker,
        protected EmbeddingService $embeddings,
        protected QdrantService $qdrant,
    ) {}

    public function createFromUpload(
        UploadedFile $file,
        string $title,
        string $sourceType = RagDocument::SOURCE_ADMIN_CURRICULUM,
        array $metadata = [],
        ?int $sourceId = null,
        ?int $createdBy = null,
    ): RagDocument {
        $path = Storage::disk('local')->putFile('rag', $file);

        return RagDocument::create([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'title' => $title,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'storage_path' => $path,
            'raw_text' => null,
            'status' => RagDocument::STATUS_PENDING,
            'metadata' => $metadata,
            'created_by' => $createdBy,
        ]);
    }

    public function createFromText(
        string $title,
        string $text,
        string $sourceType = RagDocument::SOURCE_ADMIN_CURRICULUM,
        array $metadata = [],
        ?int $sourceId = null,
        ?int $createdBy = null,
    ): RagDocument {
        return RagDocument::create([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'title' => $title,
            'original_filename' => null,
            'mime_type' => 'text/plain',
            'storage_path' => null,
            'raw_text' => $this->extractor->normalize($text),
            'status' => RagDocument::STATUS_PENDING,
            'metadata' => $metadata,
            'created_by' => $createdBy,
        ]);
    }

    public function createFromLocalPath(
        string $absolutePath,
        string $title,
        string $sourceType = RagDocument::SOURCE_ADMIN_CURRICULUM,
        array $metadata = [],
        ?int $sourceId = null,
        ?int $createdBy = null,
    ): RagDocument {
        $mimeTypes = new MimeTypes();

        $mimeType = $mimeTypes->guessMimeType($absolutePath)
            ?? 'application/octet-stream';

        $rawText = $this->extractor->extractFromPath(
            $absolutePath,
            $mimeType
        );

        return RagDocument::create([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'title' => $title,
            'original_filename' => basename($absolutePath),
            'mime_type' => $mimeType,
            'storage_path' => null,
            'raw_text' => $rawText,
            'status' => RagDocument::STATUS_PENDING,
            'metadata' => array_merge($metadata, [
                'original_path' => $absolutePath,
            ]),
            'created_by' => $createdBy,
        ]);
    }

    public function index(RagDocument $document): void
    {
        Log::info('=== RAG INDEX START ===', ['doc_id' => $document->id]);
        $document->update([
            'status' => RagDocument::STATUS_PROCESSING,
            'error_message' => null,
        ]);

        try {
            $rawText = $document->raw_text;

            if (blank($rawText)) {
                if (blank($document->storage_path)) {
                    throw new RuntimeException('Document has neither raw text nor a stored file.');
                }

                $absolutePath = Storage::disk('local')->path($document->storage_path);

                $rawText = $this->extractor->extractFromPath(
                    $absolutePath,
                    $document->mime_type ?? 'application/octet-stream'
                );
            }

            $rawText = $this->extractor->normalize((string) $rawText);

            if ($rawText === '') {
                throw new RuntimeException('No usable text could be extracted from this document.');
            }

            $document->update([
                'raw_text' => $rawText,
            ]);

            $chunks = $this->chunker->chunk($rawText);

            if ($chunks === []) {
                throw new RuntimeException('Chunking produced no chunks.');
            }

            $this->qdrant->ensureCollection();
            $this->qdrant->deleteByDocument($document->id);

            $document->chunks()->delete();

            $chunkRecords = [];

            foreach ($chunks as $index => $content) {
                $chunkRecords[] = [
                    'rag_document_id' => $document->id,
                    'chunk_index' => $index,
                    'content' => $content,
                    'character_count' => mb_strlen($content),
                    'point_id' => (string) Str::uuid(),
                    'metadata' => [
                        'title' => $document->title,
                        'source_type' => $document->source_type,
                        'source_id' => $document->source_id,
                    ],
                    'indexed_at' => null,
                ];
            }

            $document->chunks()->createMany($chunkRecords);

            $freshChunks = $document->chunks()
                ->orderBy('chunk_index')
                ->get();

            $vectors = $this->embeddings->embedMany(
                $freshChunks->pluck('content')->all()
            );

            if (count($vectors) !== $freshChunks->count()) {
                throw new RuntimeException('Embedding count does not match chunk count.');
            }

            $points = [];
            $now = now();

            foreach ($freshChunks as $index => $chunk) {
                $vector = $vectors[$index] ?? null;

                if (! is_array($vector) || $vector === []) {
                    throw new RuntimeException("Missing embedding vector for chunk #{$chunk->chunk_index}.");
                }

                $payload = array_merge(
                    $document->metadata ?? [],
                    $chunk->metadata ?? [],
                    [
                        'rag_document_id' => $document->id,
                        'rag_chunk_id' => $chunk->id,
                        'title' => $document->title,
                        'source_type' => $document->source_type,
                        'source_id' => $document->source_id,
                        'chunk_index' => $chunk->chunk_index,
                        'text' => $chunk->content,
                        'indexed_at' => $now->toIso8601String(),
                    ]
                );

                $points[] = [
                    'id' => $chunk->point_id,
                    'vector' => $vector,
                    'payload' => $payload,
                ];
            }

            // After chunking:
            Log::info('Chunks created', ['count' => count($chunks)]);

            // Before Upsert:
            Log::info('Preparing to upsert to Qdrant...', [
                'collection' => config('qdrant.collection'),
                'points_count' => count($points)
            ]);

            $this->qdrant->upsertPoints($points);

            Log::info('Qdrant Upsert SUCCESS');

            $document->chunks()->update([
                'indexed_at' => $now,
            ]);

            $document->update([
                'status' => RagDocument::STATUS_INDEXED,
                'error_message' => null,
            ]);

            Log::info('=== RAG INDEX END SUCCESS ===', ['doc_id' => $document->id]);
        } catch (Throwable $e) {
            Log::error('RAG document indexing failed', [
                'document_id' => $document->id,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $document->update([
                'status' => RagDocument::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
