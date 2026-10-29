<?php

namespace App\Services\Rag\Embeddings;

use App\Services\Rag\Contracts\EmbeddingService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiEmbeddingService implements EmbeddingService
{
    public function embedMany(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $batchSize = max(1, (int) config('rag.embedding.batch_size', 50));

        $vectors = [];

        foreach (array_chunk($texts, $batchSize) as $batch) {
            $vectors = array_merge(
                $vectors,
                $this->embedBatch($batch)
            );
        }

        return $vectors;
    }

    public function dimension(): int
    {
        return (int) config('rag.embedding.dimension', 768);
    }

    /**
     * @param array<int, string> $texts
     * @return array<int, array<int, float>>
     */
    protected function embedBatch(array $texts): array
    {
        $model = (string) config('rag.embedding.model', 'text-embedding-004');

        $modelResource = str_starts_with($model, 'models/')
            ? $model
            : 'models/' . $model;

        $requests = [];

        foreach ($texts as $text) {
            $request = [
                'model' => $modelResource,
                'content' => [
                    'parts' => [
                        [
                            'text' => $text,
                        ],
                    ],
                ],
            ];

            $outputDimensionality = config('rag.embedding.output_dimensionality');

            if ($outputDimensionality) {
                $request['outputDimensionality'] = (int) $outputDimensionality;
            }

            $requests[] = $request;
        }

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/%s:batchEmbedContents',
            $modelResource
        );

        $response = Http::withHeaders([
            'x-goog-api-key' => (string) config('gemini.providers.gemini.key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout((int) config('rag.embedding.timeout', 60))
            ->retry(
                (int) config('rag.embedding.retry_times', 3),
                fn(int $attempt): int => (int) min(1000 * (2 ** ($attempt - 1)), 4000),
                function (\Exception $exception): bool {
                    if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                        return true;
                    }

                    if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                        return in_array(
                            $exception->response->status(),
                            [429, 500, 502, 503, 504],
                            true
                        );
                    }

                    return false;
                },
                true
            )
            ->post($url, [
                'requests' => $requests,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Gemini embedding request failed: ' . ($response->body() ?: $response->status())
            );
        }

        $embeddings = $response->json('embeddings', []);

        if (! is_array($embeddings) || count($embeddings) !== count($texts)) {
            throw new RuntimeException(
                'Gemini embedding response did not contain the expected number of embeddings.'
            );
        }

        return array_map(
            static function (array $embedding): array {
                $values = $embedding['values'] ?? [];

                if (! is_array($values)) {
                    throw new RuntimeException('Gemini embedding values were not an array.');
                }

                return array_map(
                    static fn($value): float => (float) $value,
                    $values
                );
            },
            $embeddings
        );
    }
}
