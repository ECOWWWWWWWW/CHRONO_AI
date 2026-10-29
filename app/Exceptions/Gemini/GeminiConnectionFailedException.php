<?php

namespace App\Exceptions\Gemini;

final class GeminiConnectionFailedException extends GeminiException
{
    public function status(): int
    {
        return 503;
    }
}
