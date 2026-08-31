<?php

declare(strict_types=1);

namespace Suqo\Http;

use RuntimeException;

/**
 * A transport-level failure — DNS, connection, TLS, timeout. The transport maps
 * this to {@see \Suqo\Exception\NetworkError} with status 0 (§6.5), which makes
 * it retryable under §7.2.
 */
class HttpClientException extends RuntimeException
{
}
