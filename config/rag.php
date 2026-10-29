<?php

return [
    'chunk' => [
        'max_chars' => env('RAG_CHUNK_MAX_CHARS', 1200),
        'overlap' => env('RAG_CHUNK_OVERLAP', 200),
        'min_chars' => env('RAG_CHUNK_MIN_CHARS', 200),
    ],

    'embedding' => [
        'driver' => env('RAG_EMBEDDING_DRIVER', 'gemini'),
        'model' => env('GEMINI_EMBEDDING_MODEL', 'text-embedding-004'),
        'dimension' => env('GEMINI_EMBEDDING_DIMENSION', 768),
        'output_dimensionality' => env('GEMINI_EMBEDDING_OUTPUT_DIMENSIONALITY'),
        'batch_size' => env('RAG_EMBEDDING_BATCH_SIZE', 50),
        'timeout' => env('RAG_EMBEDDING_TIMEOUT', 60),
        'retry_times' => env('RAG_EMBEDDING_RETRY_TIMES', 3),
    ],

    'queue' => [
        'connection' => env('RAG_QUEUE_CONNECTION', null),
    ],

    'retrieval' => [
        'limit' => env('RAG_RETRIEVAL_LIMIT', 5),
        'score_threshold' => env('RAG_RETRIEVAL_SCORE_THRESHOLD', 0.7),
    ],
];
