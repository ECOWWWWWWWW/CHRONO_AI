<?php

namespace App\Console\Commands;

use App\Models\RagDocument;
use App\Services\Rag\RagDocumentService;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

class RagIngestFile extends Command
{
    protected $signature = 'rag:ingest
                            {file : Path to the file to ingest}
                            {--title= : Document title}
                            {--source=admin_curriculum : Source type}
                            {--metadata={} : JSON metadata}';

    protected $description = 'Ingest a local curriculum/resource file into RAG';

    public function handle(RagDocumentService $documents): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $path = realpath($path);

        if ($path === false) {
            $this->error('Unable to resolve file path.');

            return self::FAILURE;
        }

        try {
            $metadata = $this->option('metadata')
                ? json_decode($this->option('metadata'), true, 512, JSON_THROW_ON_ERROR)
                : [];
        } catch (JsonException $e) {
            $this->error('Invalid JSON metadata: ' . $e->getMessage());

            return self::FAILURE;
        }

        $title = $this->option('title') ?: pathinfo($path, PATHINFO_FILENAME);

        $source = $this->option('source') ?: RagDocument::SOURCE_ADMIN_CURRICULUM;

        $this->info("Ingesting: {$path}");

        try {
            $document = $documents->createFromLocalPath(
                absolutePath: $path,
                title: $title,
                sourceType: $source,
                metadata: $metadata,
            );

            $documents->index($document);

            $document->refresh();

            $this->info("Document ID: {$document->id}");
            $this->info("Status: {$document->status}");
            $this->info("Chunks: {$document->chunks()->count()}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Ingestion failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
