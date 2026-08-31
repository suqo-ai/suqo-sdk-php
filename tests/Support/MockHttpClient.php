<?php

declare(strict_types=1);

namespace Suqo\Tests\Support;

use LogicException;
use Suqo\Http\HttpClientInterface;
use Suqo\Http\HttpRequest;
use Suqo\Http\HttpResponse;
use Throwable;

/**
 * A scripted HTTP client. Every conformance assertion about headers, attempt
 * counts and request ids reads off {@see self::$requests}.
 */
final class MockHttpClient implements HttpClientInterface
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @var list<HttpResponse|Throwable|callable(HttpRequest): HttpResponse> */
    private array $queue = [];

    /** @param list<HttpResponse|Throwable|callable(HttpRequest): HttpResponse> $queue */
    public function __construct(array $queue = [])
    {
        $this->queue = $queue;
    }

    /**
     * @param  HttpResponse|Throwable|callable(HttpRequest): HttpResponse $entry
     * @param  int                                                       $times
     * @return $this
     */
    public function push(mixed $entry, int $times = 1): self
    {
        for ($i = 0; $i < $times; $i++) {
            $this->queue[] = $entry;
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>|string|null $body
     * @param  array<string, string>            $headers
     * @return $this
     */
    public function pushJson(int $status, array|string|null $body = null, array $headers = [], int $times = 1): self
    {
        $encoded = is_array($body) ? (string) json_encode($body) : ($body ?? '');

        return $this->push(new HttpResponse($status, $headers, $encoded), $times);
    }

    public function attempts(): int
    {
        return count($this->requests);
    }

    public function lastRequest(): HttpRequest
    {
        $last = end($this->requests);

        if ($last === false) {
            throw new LogicException('No request was made.');
        }

        return $last;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new LogicException(sprintf(
                'MockHttpClient exhausted after %d request(s); last was %s %s.',
                count($this->requests),
                $request->method,
                $request->url,
            ));
        }

        $entry = array_shift($this->queue);

        if ($entry instanceof Throwable) {
            throw $entry;
        }

        if ($entry instanceof HttpResponse) {
            return $entry;
        }

        return $entry($request);
    }
}
