<?php

declare(strict_types=1);

namespace Suqo;

/**
 * §12 — webhook signature verification.
 *
 * Standalone by requirement: no client instance, no API key, no network access,
 * so it is callable from a serverless handler.
 */
final class Webhook
{
    /**
     * §12.2 — verify a webhook signature.
     *
     * The function never raises: every failure path, malformed input included,
     * returns false (§12.3).
     *
     * @param string      $rawBody   The exact bytes received. Never a re-serialised
     *                               parse — a round-trip through a JSON decoder
     *                               changes whitespace and key order, and the
     *                               signature covers bytes, not structure.
     * @param string|null $signature The signature header value; may be absent.
     * @param string|null $timestamp The timestamp header value; may be absent.
     * @param string      $secret    The webhook signing secret.
     * @param int         $maxAge    Seconds. Backward tolerance only; forward skew
     *                               is fixed at 60 seconds and is not configurable.
     */
    public static function verify(
        string $rawBody,
        ?string $signature,
        ?string $timestamp,
        string $secret,
        int $maxAge = Constants::WEBHOOK_MAX_AGE,
    ): bool {
        if ($signature === null || $timestamp === null) {
            return false;
        }

        if (!is_numeric(trim($timestamp))) {
            return false;
        }

        $ts = (float) trim($timestamp);

        if (!is_finite($ts)) {
            return false;
        }

        $now = (float) time();

        if ($now - $ts > $maxAge) {
            return false;
        }

        if ($ts - $now > Constants::WEBHOOK_FORWARD_SKEW) {
            return false;
        }

        if (!str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $provided = substr($signature, strlen('sha256='));

        if (strlen($provided) !== 64) {
            return false;
        }

        // The delimiter is a literal "." between timestamp and body, with no
        // other separator or encoding. The timestamp is used exactly as received.
        $payload = $timestamp . '.' . $rawBody;
        $expected = hash_hmac('sha256', $payload, $secret);

        $providedBytes = self::hexDecode($provided);
        $expectedBytes = self::hexDecode($expected);

        if ($providedBytes === null || $expectedBytes === null) {
            return false;
        }

        // hash_equals is the constant-time comparison; a naive string equality
        // would be non-conformant.
        return hash_equals($expectedBytes, $providedBytes);
    }

    private static function hexDecode(string $hex): ?string
    {
        if ($hex === '' || strlen($hex) % 2 !== 0 || !ctype_xdigit($hex)) {
            return null;
        }

        $bytes = @hex2bin($hex);

        return $bytes === false ? null : $bytes;
    }

    private function __construct()
    {
    }
}
