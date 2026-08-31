<?php

declare(strict_types=1);

namespace Suqo\Http;

/**
 * §6.5 — a successful transport result.
 */
final class TransportResponse
{
    /**
     * @param mixed                 $body      Parsed JSON, or null when the body was
     *                                         absent or not valid JSON (§6.5).
     * @param array<string, string> $headers
     * @param string                $requestId The §6.4 identifier actually sent as
     *                                         X-Request-Id on this attempt.
     */
    public function __construct(
        public readonly int $status,
        public readonly mixed $body,
        public readonly array $headers,
        public readonly string $requestId,
    ) {
    }

    /**
     * The body as a wire object. Anything else — a JSON array, a scalar, an
     * unparseable body — yields an empty array rather than a type error.
     *
     * @return array<string, mixed>
     */
    public function object(): array
    {
        if (is_array($this->body) && ($this->body === [] || !array_is_list($this->body))) {
            /** @var array<string, mixed> */
            return $this->body;
        }

        return [];
    }
}
