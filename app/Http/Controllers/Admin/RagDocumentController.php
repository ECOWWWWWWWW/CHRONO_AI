<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\IndexRagDocument;
use App\Models\RagDocument;
use App\Services\Rag\RagDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RagDocumentController extends Controller
{
    public function store(Request $request, RagDocumentService $documents): JsonResponse
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf,docx,txt,md,markdown',
                'max:51200',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'source_type' => [
                'nullable',
                'string',
                'in:' . implode(',', [
                    RagDocument::SOURCE_ADMIN_CURRICULUM,
                    RagDocument::SOURCE_TEACHER_RESOURCE,
                    RagDocument::SOURCE_UPLOADED_FILE,
                ]),
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
            'metadata.school_id' => [
                'nullable',
                'integer',
            ],
            'metadata.subject' => [
                'nullable',
                'string',
                'max:255',
            ],
            'metadata.grade' => [
                'nullable',
                'string',
                'max:100',
            ],
            'metadata.chapter' => [
                'nullable',
                'string',
                'max:255',
            ],
            'metadata.visibility' => [
                'nullable',
                'string',
                'in:global,school,private',
            ],
        ]);

        $document = $documents->createFromUpload(
            file: $request->file('file'),
            title: $validated['title'],
            sourceType: $validated['source_type'] ?? RagDocument::SOURCE_ADMIN_CURRICULUM,
            metadata: $validated['metadata'] ?? [],
            createdBy: $request->user()?->id,
        );

        $job = new IndexRagDocument($document);

        if ($connection = config('rag.queue.connection')) {
            $job->onConnection($connection);
        }

        dispatch($job);

        return response()->json([
            'success' => true,
            'message' => 'Document accepted for indexing.',
            'document' => $document->only([
                'id',
                'title',
                'source_type',
                'status',
                'created_at',
            ]),
        ], 202);
    }

    public function show(RagDocument $document): JsonResponse
    {
        return response()->json([
            'success' => true,
            'document' => $document->loadCount('chunks'),
        ]);
    }
}
