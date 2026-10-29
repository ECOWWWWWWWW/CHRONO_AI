<?php

namespace App\Exceptions\Gemini;

use RuntimeException;
use Throwable;

abstract class GeminiException extends RuntimeException
{
    /**
     * Extra structured details useful for debugging.
     *
     * @var array<string, mixed>
     */
    protected array $details = [];

    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        string $message = '',
        array $details = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->details = $details;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }

    /**
     * HTTP status the controller should return for this exception.
     */
    public function status(): int
    {
        return 500;
    }
}
