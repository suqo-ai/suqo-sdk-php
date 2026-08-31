<?php

declare(strict_types=1);

namespace Suqo\Http;

use CurlHandle;

/**
 * The platform default HTTP client (§4.1): cURL, which is the only extension
 * able to satisfy both the per-request timeout and the mid-flight cancellation
 * §6.5 demands.
 *
 * Cancellation is implemented with a progress callback that aborts the transfer,
 * producing CURLE_ABORTED_BY_CALLBACK — kept distinct from CURLE_OPERATION_
 * TIMEDOUT so the transport can raise CancelledError rather than NetworkError.
 */
final class CurlHttpClient implements HttpClientInterface
{
    /** CURLE_ABORTED_BY_CALLBACK. Spelled out because the constant is not defined on every build. */
    public const ABORTED_BY_CALLBACK = 42;

    /** CURLE_OPERATION_TIMEDOUT. */
    public const OPERATION_TIMEDOUT = 28;

    /** @param array<int, mixed> $curlOptions Extra options, applied before the mandatory ones. */
    public function __construct(
        private readonly array $curlOptions = [],
    ) {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $handle = curl_init();
        if ($handle === false) {
            throw new HttpClientException('Unable to initialise cURL.');
        }

        $headers = [];
        $cancelled = false;

        // No curl_close(): the handle is released when it goes out of scope, and
        // the function has been a deprecated no-op since PHP 8.0.
        $this->configure($handle, $request, $headers, $cancelled);

        $body = curl_exec($handle);
        $errno = curl_errno($handle);

        if ($body === false || $errno !== 0) {
            $message = curl_error($handle);

            if ($cancelled || $errno === self::ABORTED_BY_CALLBACK) {
                throw new HttpCancelledException(
                    $message === '' ? 'Request cancelled by caller.' : $message,
                    $errno,
                );
            }

            throw new HttpClientException(
                $message === '' ? sprintf('cURL error %d.', $errno) : $message,
                $errno,
            );
        }

        /** @var int $status */
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new HttpResponse($status, $headers, is_string($body) ? $body : '');
    }

    /**
     * @param array<string, string> $headers Collected response headers, by reference.
     */
    private function configure(
        CurlHandle $handle,
        HttpRequest $request,
        array &$headers,
        bool &$cancelled,
    ): void {
        $timeoutMs = (int) round($request->timeout * 1000);
        $cancellation = $request->cancellation;

        $options = $this->curlOptions;

        $options[CURLOPT_URL] = $request->url;
        $options[CURLOPT_CUSTOMREQUEST] = $request->method;
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_FOLLOWLOCATION] = false;
        $options[CURLOPT_NOSIGNAL] = true;
        $options[CURLOPT_TIMEOUT_MS] = max(1, $timeoutMs);
        $options[CURLOPT_CONNECTTIMEOUT_MS] = max(1, $timeoutMs);
        $options[CURLOPT_HTTPHEADER] = self::flattenHeaders($request->headers);

        if ($request->body !== null) {
            $options[CURLOPT_POSTFIELDS] = $request->body;
        }

        $options[CURLOPT_HEADERFUNCTION] = static function (mixed $handle, string $line) use (&$headers): int {
            $length = strlen($line);
            $position = strpos($line, ':');

            if ($position !== false) {
                $name = strtolower(trim(substr($line, 0, $position)));
                $headers[$name] = trim(substr($line, $position + 1));
            }

            return $length;
        };

        $options[CURLOPT_NOPROGRESS] = false;
        $options[CURLOPT_PROGRESSFUNCTION] = static function () use ($cancellation, &$cancelled): int {
            if ($cancellation->isCancelled()) {
                $cancelled = true;

                return 1;
            }

            return 0;
        };

        curl_setopt_array($handle, $options);
    }

    /**
     * @param  array<string, string> $headers
     * @return list<string>
     */
    private static function flattenHeaders(array $headers): array
    {
        $flat = [];
        foreach ($headers as $name => $value) {
            $flat[] = $name . ': ' . $value;
        }

        return $flat;
    }
}
