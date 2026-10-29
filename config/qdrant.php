<?php

return [
    'host' => env('QDRANT_HOST', '127.0.0.1'),
    'port' => env('QDRANT_PORT', 6333),
    'collection' => env('QDRANT_COLLECTION', 'chrono_rag'),
    'api_key' => env('QDRANT_API_KEY'),
    'timeout' => env('QDRANT_TIMEOUT', 30),
    'distance' => env('QDRANT_DISTANCE', 'Cosine'),
];