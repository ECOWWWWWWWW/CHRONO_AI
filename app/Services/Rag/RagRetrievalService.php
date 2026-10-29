<?php

namespace App\Services\Rag;

use App\Models\User;
use App\Services\Rag\Contracts\EmbeddingService;
use Illuminate\Support\Facades\Log;

class RagRetrievalService
{
    public function __construct(
        protected EmbeddingService $embedder,
        protected QdrantService $qdrant,
    ) {}

    /**
     * Retrieve relevant context for a given prompt.
     *
     * @param string $prompt The user's question.
     * @param User|null $user The authenticated user (for visibility filtering).
     * @return string|null The formatted context block, or null if nothing found.
     */
    public function retrieveContext(string $prompt, ?User $user = null): ?string
    {
        if (blank($prompt)) {
            return null;
        }

        try {
            // 1. Embed the prompt
            [$vector] = $this->embedder->embedMany([$prompt]);

            if (!$vector) {
                Log::warning('RAG Retrieval: Failed to embed prompt.');
                return null;
            }

            // 2. Build Qdrant filter based on user permissions
            $filter = $this->buildVisibilityFilter($user);

            // 3. Search Qdrant
            $hits = $this->qdrant->search(
                vector: $vector,
                limit: (int) config('rag.retrieval.limit', 5),
                filter: $filter
            );

            if (empty($hits)) {
                return null;
            }

            // 4. Filter by score threshold and format text
            $threshold = (float) config('rag.retrieval.score_threshold', 0.7);
            $relevantChunks = [];

            foreach ($hits as $hit) {
                if (($hit['score'] ?? 0) < $threshold) {
                    continue;
                }

                $payload = $hit['payload'] ?? [];
                $text = $payload['text'] ?? '';

                if (filled($text)) {
                    $relevantChunks[] = [
                        'source' => $payload['title'] ?? 'Unknown Source',
                        'type' => $payload['source_type'] ?? 'unknown',
                        'text' => trim($text),
                    ];
                }
            }

            if (empty($relevantChunks)) {
                return null;
            }

            // 5. Format into a single context string
            return $this->formatContextString($relevantChunks);
        } catch (\Throwable $e) {
            Log::error('RAG Retrieval Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fail gracefully: If retrieval fails, proceed without context
            return null;
        }
    }

    /**
     * Builds the Qdrant filter structure for visibility rules.
     */
    protected function buildVisibilityFilter(?User $user): array
    {
        // Default: Only allow GLOBAL documents if no user is present
        if (!$user) {
            return [
                'must' => [
                    ['key' => 'visibility', 'match' => ['value' => 'global']],
                ],
            ];
        }

        $schoolId = $user->school_id ?? null;
        $userId = $user->id;

        $conditions = [
            ['key' => 'visibility', 'match' => ['value' => 'global']],
        ];

        if ($schoolId) {
            $conditions[] = [
                'should' => [
                    ['key' => 'visibility', 'match' => ['value' => 'school']],
                    ['key' => 'school_id', 'match' => ['value' => $schoolId]],
                ],
            ];
        }

        $conditions[] = [
            'should' => [
                ['key' => 'visibility', 'match' => ['value' => 'private']],
                ['key' => 'owner_id', 'match' => ['value' => $userId]],
            ],
        ];

        return [
            'must' => [
                ['should' => $conditions],
            ],
        ];
    }

    /**
     * Formats retrieved chunks into a readable context block for the LLM.
     */
    protected function formatContextString(array $chunks): string
    {
        $lines = [];

        foreach ($chunks as $index => $chunk) {
            $header = sprintf(
                "[Source %d: %s (%s)]",
                $index + 1,
                htmlspecialchars($chunk['source']),
                strtoupper($chunk['type'])
            );

            $lines[] = $header;
            $lines[] = $chunk['text'];
            $lines[] = ""; // Empty line separator
        }

        return implode("\n", $lines);
    }
}
