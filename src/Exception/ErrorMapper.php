<?php

declare(strict_types=1);

namespace Suqo\Exception;

/**
 * §8.3 / §8.4 — body classification and status mapping.
 *
 * I3: this is reached only from the transport layer. Resources never map status
 * codes.
 */
final class ErrorMapper
{
    private const KIND_NONE = 'none';
    private const KIND_KYC = 'kyc';
    private const KIND_DETAIL = 'detail';
    private const KIND_FIELD = 'field';

    /**
     * §8.4 — map an HTTP status and parsed body onto an error type.
     *
     * @param mixed      $body       Parsed response body, or null when the body
     *                               was absent or not valid JSON (§6.5).
     * @param float|null $retryAfter Seconds, from the Retry-After header (§6.7).
     */
    public static function map(
        int $status,
        mixed $body,
        string $requestId,
        ?float $retryAfter = null,
    ): SuqoError {
        $c = self::classify($body);
        $classified = $c['message'];

        return match (true) {
            $status === 401 => new AuthenticationError(
                $classified ?? 'Authentication failed.',
                $status,
                $requestId,
                $body,
                [],
                $retryAfter,
            ),
            $status === 403 && $c['kind'] === self::KIND_KYC => new KycRequiredError(
                (string) $classified,
                $status,
                $requestId,
                $body,
                [],
                $retryAfter,
                $c['kycStatus'],
            ),
            $status === 403 => new SuqoError(
                'Unexpected status 403.',
                $status,
                $requestId,
                $body,
                [],
                $retryAfter,
            ),
            $status === 404 => new NotFoundError(
                $classified ?? 'Not found.',
                $status,
                $requestId,
                $body,
                [],
                $retryAfter,
            ),
            $status === 400 => new ValidationError(
                $classified ?? 'Validation failed.',
                $status,
                $requestId,
                $body,
                $c['kind'] === self::KIND_FIELD ? $c['fieldErrors'] : [],
                $retryAfter,
            ),
            $status === 429 => new RateLimitError(
                $classified ?? 'Rate limited.',
                $status,
                $requestId,
                $body,
                [],
                $retryAfter,
            ),
            $status >= 500 => new ServerError(
                sprintf('Server error (%d).', $status),
                $status,
                $requestId,
                $body,
                [],
                $retryAfter,
            ),
            default => new SuqoError(
                sprintf('Unexpected status %d.', $status),
                $status,
                $requestId,
                $body,
                [],
                $retryAfter,
            ),
        };
    }

    /**
     * §8.3 — classify a parsed body. Order is normative.
     *
     * @return array{kind: string, message: string|null, fieldErrors: array<string, list<string>>, kycStatus: string|null}
     */
    private static function classify(mixed $body): array
    {
        $none = ['kind' => self::KIND_NONE, 'message' => null, 'fieldErrors' => [], 'kycStatus' => null];

        if (!self::isWireObject($body)) {
            return $none;
        }

        /** @var array<string, mixed> $body */

        // 1. KYC: both keys present, both strings.
        $statusCode = $body['status_code'] ?? null;
        $message = $body['message'] ?? null;
        if (is_string($statusCode) && is_string($message)) {
            return [
                'kind' => self::KIND_KYC,
                'message' => $message,
                'fieldErrors' => [],
                'kycStatus' => $statusCode,
            ];
        }

        // 2. Detail: exactly one key, named "detail", string-valued.
        $detail = $body['detail'] ?? null;
        if (is_string($detail) && count($body) === 1) {
            return [
                'kind' => self::KIND_DETAIL,
                'message' => $detail,
                'fieldErrors' => [],
                'kycStatus' => null,
            ];
        }

        // 3. Field errors: collect string and string-list values.
        // B12: PHP's json_decode preserves JSON document order, so "first
        // entry" below is the first key in the response body.
        $fields = [];
        foreach ($body as $key => $value) {
            if (is_string($value)) {
                $fields[(string) $key] = [$value];
            } elseif (self::isStringList($value)) {
                /** @var list<string> $value */
                $fields[(string) $key] = array_values($value);
            }
        }

        $first = null;
        foreach ($fields as $values) {
            if ($values !== []) {
                $first = $values[0];
                break;
            }
        }

        return [
            'kind' => self::KIND_FIELD,
            'message' => $first,
            'fieldErrors' => $fields,
            'kycStatus' => null,
        ];
    }

    /**
     * A decoded JSON object. json_decode(assoc) renders `{}` and `[]`
     * identically, so an empty array is treated as an object with no keys; both
     * classify to the same outcome, so the ambiguity is not observable.
     */
    private static function isWireObject(mixed $body): bool
    {
        return is_array($body) && ($body === [] || !array_is_list($body));
    }

    private static function isStringList(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }

    private function __construct()
    {
    }
}
