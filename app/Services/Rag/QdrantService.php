<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class QdrantService
{
    public function ensureCollection(): void
    {
        $collection = $this->collection();
        $dimension = (int) config('rag.embedding.dimension', 768);
        $distance = (string) config('qdrant.distance', 'Cosine');

        $response = $this->client()->put("/collections/{$collection}", [
            'vectors' => [
                'size' => $dimension,
                'distance' => $distance,
            ],
            'on_disk_payload' => true,
        ]);

        if ($response->successful()) {
            return;
        }

        $body = strtolower((string) $response->body());

        if (
            str_contains($body, 'already exists') ||
            str_contains($body, 'exists') ||
            $response->status() === 409
        ) {
            return;
        }

        throw new RuntimeException(
            "Unable to create Qdrant collection [{$collection}]: " . $response->body()
        );
    }

    /**
     * @param array<int, array{id: string, vector: array<int, float>, payload: array<string, mixed>}> $points
     */
    public function upsertPoints(array $points): void
    {
        if ($points === []) {
            return;
        }

        $collection = $this->collection();

        foreach (array_chunk($points, 50) as $batch) {
            $response = $this->client()->put("/collections/{$collection}/points?wait=true", [
                'points' => $batch,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException(
                    'Qdrant point upsert failed: ' . $response->body()
                );
            }
        }
    }

    public function deleteByDocument(int $documentId): void
    {
        $collection = $this->collection();

        $response = $this->client()->post("/collections/{$collection}/points/delete?wait=true", [
            'filter' => [
                'must' => [
                    [
                        'key' => 'rag_document_id',
                        'match' => [
                            'value' => $documentId,
                        ],
                    ],
                ],
            ],
        ]);

        if (! $response->successful()) {
            $body = strtolower((string) $response->body());

            if (str_contains($body, 'not found') || $response->status() === 404) {
                return;
            }

            throw new RuntimeException(
                'Qdrant point delete failed: ' . $response->body()
            );
        }
    }

    /**
     * Useful for Phase 4 retrieval.
     *
     * @param array<int, float> $vector
     * @param array<string, mixed> $filter
     * @return array<int, array<string, mixed>>
     */
    public function search(array $vector, int $limit = 5, array $filter = []): array
    {
        $collection = $this->collection();

        $body = [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
        ];

        if ($filter !== []) {
            $body['filter'] = $filter;
        }

        $response = $this->client()->post("/collections/{$collection}/points/search", $body);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Qdrant search failed: ' . $response->body()
            );
        }

        return array_map(
            static function (array $hit): array {
                return [
                    'id' => $hit['id'] ?? null,
                    'score' => $hit['score'] ?? null,
                    'payload' => $hit['payload'] ?? [],
                ];
            },
            $response->json('result', [])
        );
    }

    protected function client(): \Illuminate\Http\Client\PendingRequest
    {
        $baseUrl = sprintf(
            'http://%s:%d',
            config('qdrant.host', '127.0.0.1'),
            config('qdrant.port', 6333)
        );

        $request = Http::baseUrl($baseUrl)
            ->timeout((int) config('qdrant.timeout', 30))
            ->acceptJson()
            ->asJson();

        $apiKey = config('qdrant.api_key');

        if (filled($apiKey)) {
            $request = $request->withHeader('api-key', (string) $apiKey);
        }

        return $request;
    }

    protected function collection(): string
    {
        return (string) config('qdrant.collection', 'chrona_rag');
    }
}
