<?php

declare(strict_types=1);

namespace Suqo\Http;

/**
 * The request was aborted because the caller's cancellation token fired. §6.5
 * requires this to stay distinguishable from a timeout: the transport maps it to
 * {@see \Suqo\Exception\CancelledError}, which is never retried.
 */
final class HttpCancelledException extends HttpClientException
{
}
