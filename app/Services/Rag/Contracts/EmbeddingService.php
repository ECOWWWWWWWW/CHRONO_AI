<?php

namespace App\Services\Rag\Contracts;

interface EmbeddingService
{
    /**
     * @param array<int, string> $texts
     * @return array<int, array<int, float>>
     */
    public function embedMany(array $texts): array;

    public function dimension(): int;
}
