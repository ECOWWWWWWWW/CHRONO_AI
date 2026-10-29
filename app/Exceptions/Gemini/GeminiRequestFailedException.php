<?php

namespace App\Exceptions\Gemini;

use Throwable;

final class GeminiRequestFailedException extends GeminiException
{
    private int $upstreamStatus;

    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        string $message,
        int $upstreamStatus,
        array $details = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $details, $code, $previous);

        $this->upstreamStatus = $upstreamStatus;
    }

    public function upstreamStatus(): int
    {
        return $this->upstreamStatus;
    }

    /**
     * We intentionally return 500 to your frontend for API request failures.
     *
     * The real Gemini status is preserved inside:
     * - details.upstream_status
     * - upstreamStatus()
     *
     * This keeps your frontend contract stable while still allowing debugging.
     */
    public function status(): int
    {
        return 500;
    }
}
