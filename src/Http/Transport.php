<?php

declare(strict_types=1);

namespace Suqo\Http;

use Closure;
use Suqo\Cancellation;
use Suqo\Config;
use Suqo\Exception\CancelledError;
use Suqo\Exception\ErrorMapper;
use Suqo\Exception\NetworkError;
use Suqo\Exception\SuqoError;
use Suqo\Logging\Logger;

/**
 * §6 — the transport.
 *
 * I1: {@see self::attempt()} is the single HTTP call site in the SDK. Every
 * request, relative or absolute, retried or not, passes through it.
 * I3: status-code mapping happens here, never in a resource.
 */
final class Transport
{
    private readonly Closure $requestIdFactory;

    /**
     * @param Closure(): string|null $requestIdFactory Injection point for tests
     *        only; never a public option.
     */
    public function __construct(
        private readonly Config $config,
        private readonly UrlBuilder $urls,
        private readonly RetryPolicy $retry,
        private readonly Logger $logger,
        ?Closure $requestIdFactory = null,
    ) {
        $this->requestIdFactory = $requestIdFactory ?? Closure::fromCallable([self::class, 'uuid4']);
    }

    /**
     * A request against a path from the §6.1 endpoint table, wrapped in the §7
     * retry policy.
     *
     * @param array<string, mixed>|null                 $body
     * @param array<string, string|int|float|bool|null> $query
     *
     * @throws SuqoError
     */
    public function request(
        string $method,
        string $path,
        ?array $body = null,
        array $query = [],
        ?Cancellation $cancellation = null,
    ): TransportResponse {
        $cancellation ??= Cancellation::none();
        $url = $this->urls->build($path, $query);

        return $this->retry->execute(
            $method,
            $cancellation,
            fn (): TransportResponse => $this->attempt($method, $url, $body, $cancellation),
        );
    }

    /**
     * §6.2 — the absolute URL for a path from the §6.1 endpoint table. Exposed so
     * that auto-paging can start from an absolute URL and take the same
     * {@see self::getAbsolute()} path for every page, page 1 included (§11.2).
     *
     * @param array<string, string|int|float|bool|null> $query
     */
    public function url(string $path, array $query = []): string
    {
        return $this->urls->build($path, $query);
    }

    /**
     * §6.6 — an absolute-URL GET for following server-supplied pagination links.
     * Applies the same headers, timeout, cancellation, error mapping and retry
     * policy as {@see self::request()}.
     *
     * @throws SuqoError
     */
    public function getAbsolute(string $url, ?Cancellation $cancellation = null): TransportResponse
    {
        $cancellation ??= Cancellation::none();

        // Every request carries the API key, and this is the only entry point
        // that accepts a URL the SDK did not build. A `next` link pointing off
        // host — a backend bug, a compromised proxy, tampering — would hand the
        // key to that host. The check precedes the retry loop because a refused
        // URL cannot succeed on a second attempt.
        $this->assertSameOrigin($url);

        return $this->retry->execute(
            'GET',
            $cancellation,
            fn (): TransportResponse => $this->attempt('GET', $url, null, $cancellation),
        );
    }

    /**
     * §6.5 — the request lifecycle. One attempt; one request id.
     *
     * @param array<string, mixed>|null $body
     *
     * @throws SuqoError
     */
    private function attempt(
        string $method,
        string $url,
        ?array $body,
        Cancellation $cancellation,
    ): TransportResponse {
        if ($cancellation->isCancelled()) {
            throw new CancelledError('Request cancelled by caller.');
        }

        // §6.4 — one identifier per attempt, sent and reported. Each retry
        // attempt is a distinct request and carries a fresh id.
        $requestId = ($this->requestIdFactory)();
        $serialised = $body === null ? null : self::serialise($body);
        $headers = $this->buildHeaders($requestId, $serialised !== null);

        $this->logger->debug('request', ['method' => $method, 'url' => $url, 'request_id' => $requestId]);

        $startedAt = hrtime(true);

        try {
            $response = $this->config->httpClient->send(new HttpRequest(
                $method,
                $url,
                $headers,
                $serialised,
                $this->config->timeout,
                $cancellation,
            ));
        } catch (HttpCancelledException $e) {
            throw new CancelledError($e->getMessage(), 0, $requestId);
        } catch (HttpClientException $e) {
            throw new NetworkError($e->getMessage(), 0, $requestId);
        }

        $parsed = self::parseJson($response->body);
        $elapsedMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $this->logger->debug('response', [
            'status' => $response->status,
            'request_id' => $requestId,
            'elapsed_ms' => $elapsedMs,
        ]);

        // §6.5 — 3xx included. No HTTP client the SDK drives follows redirects
        // (CurlHttpClient forces CURLOPT_FOLLOWLOCATION off), so a 3xx arrives
        // here intact and would otherwise be decoded as if it were the payload.
        // It also surfaces an injected PSR-18 client configured not to follow
        // them, which is the safe configuration but produces no usable body.
        if ($response->status >= 300) {
            throw ErrorMapper::map(
                $response->status,
                $parsed,
                $requestId,
                self::parseRetryAfter($response->header('Retry-After')),
            );
        }

        return new TransportResponse($response->status, $parsed, $response->headers, $requestId);
    }

    /**
     * §6.6 — an absolute URL may only address the origin this client is
     * configured for.
     *
     * Equality, not an allowlist of the two known hosts: a client is pinned to
     * one environment by its key prefix (§4.2), so a sandbox client following a
     * link to the live host would be wrong even though that host is legitimate
     * for some other client. The expected origin is derived from the configured
     * base URL rather than named here, so no URL literal escapes the §6.1
     * endpoint table (I4).
     *
     * Raised as a NetworkError so a caller still only ever sees a SuqoError. No
     * request id: nothing was sent.
     *
     * @throws NetworkError
     */
    private function assertSameOrigin(string $url): void
    {
        if (self::origin($url) !== self::origin($this->config->baseUrl)) {
            throw new NetworkError(
                'Refusing to send a request to a host that does not match the configured '
                . 'base URL. This SDK never sends its API key to a different host.',
            );
        }
    }

    /**
     * Scheme, host and explicit port — the comparable part of a URL. Scheme and
     * host are case-insensitive per RFC 3986, so both are lowercased.
     *
     * Null for anything unparseable or missing an origin, which makes the
     * comparison in {@see self::assertSameOrigin()} fail closed: a malformed
     * next link is refused rather than handed to the HTTP client.
     */
    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return strtolower($parts['scheme']) . '://' . strtolower($parts['host'])
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * §6.3 — header construction.
     *
     * @return array<string, string>
     */
    private function buildHeaders(string $requestId, bool $hasBody): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->config->apiKey,
            'X-Request-Id' => $requestId,
        ];

        if ($hasBody) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    /**
     * §6.7 — Retry-After, interpreted as seconds. A non-numeric value (an
     * HTTP-date, say) yields null rather than an error.
     */
    private static function parseRetryAfter(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        $seconds = (float) $value;

        return $seconds < 0.0 ? null : $seconds;
    }

    /**
     * §6.5 — a body that fails to parse as JSON becomes null, not an exception.
     */
    private static function parseJson(string $raw): mixed
    {
        if (trim($raw) === '') {
            return null;
        }

        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function serialise(array $body): string
    {
        // An empty body must serialise as `{}`, not `[]`.
        $encoded = json_encode($body === [] ? new \stdClass() : $body, JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '{}' : $encoded;
    }

    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
