<?php

namespace App\Exceptions\Gemini;

final class GeminiInvalidResponseException extends GeminiException
{
    public function status(): int
    {
        return 502;
    }
}
