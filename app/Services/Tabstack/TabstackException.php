<?php

namespace App\Services\Tabstack;

use RuntimeException;
use Throwable;

/**
 * Carries whether the failure is worth another attempt. A dropped connection
 * or an overloaded API is; a rejected key or a malformed query is not, and
 * retrying one of those only burns the reservation for longer.
 */
class TabstackException extends RuntimeException
{
    public function __construct(
        string $message,
        protected bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /** The request never completed: connection refused, DNS, timeout. */
    public static function transport(Throwable $previous): self
    {
        return new self("Tabstack request failed: {$previous->getMessage()}", retryable: true, previous: $previous);
    }

    /** Tabstack answered, but not with a stream. */
    public static function response(int $status, string $body): self
    {
        return new self(
            "Tabstack returned HTTP {$status}: ".str($body)->limit(500),
            // 429 and 5xx are the API asking for patience; 4xx is the request
            // being wrong, and it will be just as wrong in thirty seconds.
            retryable: $status === 429 || $status >= 500,
        );
    }

    /** The stream stopped before a report arrived, with no error event. */
    public static function truncatedStream(): self
    {
        return new self('The research stream ended before a report was produced.', retryable: true);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
