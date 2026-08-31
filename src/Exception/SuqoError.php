<?php

declare(strict_types=1);

namespace Suqo\Exception;

use RuntimeException;

/**
 * §8.1 — the single base type every SDK error derives from. Also raised
 * directly for unmapped statuses (§8.4).
 *
 * B6: errors are represented as a PHP exception hierarchy rooted here. The
 * spec's "Error" suffix is kept verbatim rather than PHP's "Exception" idiom so
 * that type names read the same across bindings (N6 permits either); every type
 * is nevertheless a \Throwable and can be caught as one.
 */
class SuqoError extends RuntimeException
{
    /** §8.2 — HTTP status, or 0 for non-HTTP failures. */
    public readonly int $status;

    /** §8.2 — request id from §6.4; empty for errors raised before a request. */
    public readonly string $requestId;

    /**
     * §8.2 / N8 — the parsed response body with wire names preserved. Null when
     * there was no body, or the body was not valid JSON (§6.5).
     */
    public readonly mixed $rawBody;

    /**
     * §8.2 — always present, empty when not applicable.
     *
     * @var array<string, list<string>>
     */
    public readonly array $fieldErrors;

    /** §8.2 — seconds, from the Retry-After response header (§6.7). */
    public readonly ?float $retryAfter;

    /**
     * @param array<string, list<string>> $fieldErrors
     */
    public function __construct(
        string $message,
        int $status = 0,
        string $requestId = '',
        mixed $rawBody = null,
        array $fieldErrors = [],
        ?float $retryAfter = null,
    ) {
        parent::__construct($message, $status);

        $this->status = $status;
        $this->requestId = $requestId;
        $this->rawBody = $rawBody;
        $this->fieldErrors = $fieldErrors;
        $this->retryAfter = $retryAfter;
    }
}
