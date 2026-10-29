<?php

namespace App\Jobs;

use App\Models\RagDocument;
use App\Services\Rag\RagDocumentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IndexRagDocument implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public function __construct(
        public RagDocument $document,
    ) {}

    public function handle(RagDocumentService $documents): void
    {
        $documents->index($this->document);
    }
}
