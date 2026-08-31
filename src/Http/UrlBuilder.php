<?php

declare(strict_types=1);

namespace Suqo\Http;

/**
 * §6.2 — the URL builder. Owns the trailing slash (§6.1) so no endpoint literal
 * ever carries one.
 */
final class UrlBuilder
{
    public function __construct(
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @param array<string, string|int|float|bool|null> $query Entries whose value
     *        is null are omitted entirely (U3).
     */
    public function build(string $path, array $query = []): string
    {
        $base = rtrim($this->baseUrl, '/');

        if (!str_ends_with($path, '/')) {
            $path .= '/';
        }

        $qs = self::queryString($query);

        return $base . $path . ($qs === '' ? '' : '?' . $qs);
    }

    /**
     * @param array<string, string|int|float|bool|null> $query
     */
    private static function queryString(array $query): string
    {
        $parts = [];

        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }

            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode(self::scalarToString($value));
        }

        return implode('&', $parts);
    }

    private static function scalarToString(string|int|float|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
