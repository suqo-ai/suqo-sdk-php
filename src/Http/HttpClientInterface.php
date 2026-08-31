<?php

declare(strict_types=1);

namespace Suqo\Http;

/**
 * B5 — the HTTP client injection point.
 *
 * An SDK-owned interface rather than PSR-18, because §6.5 requires per-request
 * timeouts and mid-flight cancellation and PSR-18 can express neither.
 * {@see Psr18HttpClient} adapts a PSR-18 client for callers who want one.
 */
interface HttpClientInterface
{
    /**
     * @throws HttpCancelledException When the request was aborted because the
     *                               caller's cancellation token fired.
     * @throws HttpClientException    On any other transport failure, timeouts
     *                               included.
     */
    public function send(HttpRequest $request): HttpResponse;
}
