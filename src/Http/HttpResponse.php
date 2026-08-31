<?php

declare(strict_types=1);

namespace Suqo\Http;

/**
 * A raw HTTP response as returned by the injected client (B5). No parsing, no
 * error mapping — both belong to the transport (I3).
 */
final class HttpResponse
{
    /** @var array<string, string> Header names lower-cased. */
    public readonly array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        array $headers,
        public readonly string $body,
    ) {
        $normalised = [];
        foreach ($headers as $name => $value) {
            $normalised[strtolower($name)] = $value;
        }
        $this->headers = $normalised;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
