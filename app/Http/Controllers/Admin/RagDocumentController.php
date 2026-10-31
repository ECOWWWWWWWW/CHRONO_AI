<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\IndexRagDocument;
use App\Models\RagDocument;
use App\Services\Rag\RagDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class RagDocumentController extends Controller
{
    public function store(Request $request, RagDocumentService $documents): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,docx,txt,md,markdown', 'max:51200'],
            'title' => ['required', 'string', 'max:255'],
            'source_type' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        // 1. Get metadata from request
        $metadata = $validated['metadata'] ?? [];

        // 2. FORCE 'global' visibility for all Admin uploads
        // Teachers/Master Teachers cannot upload, so anything here is Official.
        $metadata['visibility'] = 'global';

        // Optional: Tag who uploaded it for audit logs (not for filtering)
        $metadata['uploaded_by_role'] = $request->user()->role;

        try {
            $document = $documents->createFromUpload(
                file: $request->file('file'),
                title: $validated['title'],
                sourceType: $validated['source_type'] ?? RagDocument::SOURCE_ADMIN_CURRICULUM,
                metadata: $metadata,
                createdBy: $request->user()?->id,
            );

            // Synchronous execution for testing
            $job = new IndexRagDocument($document);
            $job->handle(app(RagDocumentService::class));

            $document->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Document processed successfully.',
                'document' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'status' => $document->status,
                    'chunks_count' => $document->chunks()->count(),
                    'metadata_used' => $document->metadata,
                ]
            ], 200);
        } catch (Throwable $e) {
            Log::error('RAG Ingestion Failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Ingestion failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function index(): JsonResponse
    {
        // Fetch the last 10 documents, ordered by newest first
        $docs = RagDocument::latest()->take(10)->get();

        return response()->json($docs->map(fn($doc) => [
            'id' => $doc->id,
            'title' => $doc->title,
            'status' => $doc->status,
            'chunks' => $doc->chunks()->count(),
            'created_at' => $doc->created_at->diffForHumans(),
        ]));
    }

    public function show(RagDocument $document): JsonResponse
    {
        return response()->json([
            'success' => true,
            'document' => $document->loadCount('chunks'),
        ]);
    }
}
