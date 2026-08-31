<?php

declare(strict_types=1);

namespace Suqo\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

/**
 * Adapter for callers who already have a PSR-18 client wired into their
 * application (B5).
 *
 * Caveat, and the reason PSR-18 is not the default: PSR-18 has no vocabulary for
 * per-request timeouts or mid-flight cancellation. The adapter therefore honours
 * cancellation only at the boundaries — before the call and immediately after —
 * and the timeout must be configured on the injected client itself. Where §6.5's
 * timing guarantees matter, use {@see CurlHttpClient}.
 */
final class Psr18HttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        if ($request->cancellation->isCancelled()) {
            throw new HttpCancelledException('Request cancelled by caller.');
        }

        $psrRequest = $this->requestFactory->createRequest($request->method, $request->url);

        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        if ($request->body !== null) {
            $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($request->body));
        }

        try {
            $psrResponse = $this->client->sendRequest($psrRequest);
        } catch (Throwable $e) {
            if ($request->cancellation->isCancelled()) {
                throw new HttpCancelledException($e->getMessage(), (int) $e->getCode(), $e);
            }

            throw new HttpClientException($e->getMessage(), (int) $e->getCode(), $e);
        }

        if ($request->cancellation->isCancelled()) {
            throw new HttpCancelledException('Request cancelled by caller.');
        }

        $headers = [];
        foreach ($psrResponse->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', $values);
        }

        return new HttpResponse($psrResponse->getStatusCode(), $headers, (string) $psrResponse->getBody());
    }
}
