<?php

namespace App\Providers;

use App\Services\Rag\Contracts\EmbeddingService;
use App\Services\Rag\Embeddings\GeminiEmbeddingService;
use Illuminate\Support\ServiceProvider;

class RagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EmbeddingService::class, function ($app) {
            $driver = config('rag.embedding.driver', 'gemini');

            return match ($driver) {
                'gemini' => $app->make(GeminiEmbeddingService::class),
                default => throw new \RuntimeException("Unsupported embedding driver [{$driver}]."),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
