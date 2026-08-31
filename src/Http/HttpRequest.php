<?php

declare(strict_types=1);

namespace Suqo\Http;

use Suqo\Cancellation;

/**
 * An outbound HTTP request as handed to the injected client (B5).
 *
 * Headers are already fully built (§6.3); the client MUST send them verbatim
 * and MUST NOT add an Authorization or Content-Type header of its own.
 */
final class HttpRequest
{
    /**
     * @param array<string, string> $headers
     * @param string|null           $body    Serialised body, or null when absent.
     * @param float                 $timeout Seconds.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers,
        public readonly ?string $body,
        public readonly float $timeout,
        public readonly Cancellation $cancellation,
    ) {
    }
}
