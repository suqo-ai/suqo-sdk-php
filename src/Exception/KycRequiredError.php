<?php

declare(strict_types=1);

namespace Suqo\Exception;

/**
 * §8.1 — 403 with a KYC-shaped body (§8.3).
 */
final class KycRequiredError extends SuqoError
{
    /**
     * §8.2 / §3 — the wire field `status_code`, surfaced as `kycStatus` because
     * it sits next to the HTTP status and means something different. The raw
     * body still carries `status_code` (N8).
     */
    public readonly ?string $kycStatus;

    /**
     * @param array<string, list<string>> $fieldErrors
     */
    public function __construct(
        string $message,
        int $status = 403,
        string $requestId = '',
        mixed $rawBody = null,
        array $fieldErrors = [],
        ?float $retryAfter = null,
        ?string $kycStatus = null,
    ) {
        parent::__construct($message, $status, $requestId, $rawBody, $fieldErrors, $retryAfter);

        $this->kycStatus = $kycStatus;
    }
}
