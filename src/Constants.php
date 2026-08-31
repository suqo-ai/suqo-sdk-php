<?php

declare(strict_types=1);

namespace Suqo;

/**
 * Specification constants (§4.3, §4.4, §7.1).
 *
 * The verbatim message strings MUST NOT be paraphrased, localised or reworded.
 */
final class Constants
{
    /** @var string §4.3 */
    public const LIVE_URL = 'https://be.suqo.ai';

    /** @var string §4.3 */
    public const SANDBOX_URL = 'https://test-be.suqo.ai';

    /** @var string §4.3 */
    public const LIVE_KEY_PREFIX = 'su_key_';

    /** @var string §4.3 */
    public const SANDBOX_KEY_PREFIX = 'su_test_key_';

    /** @var string Environment variable holding the API key. */
    public const ENV_API_KEY = 'SUQO_API_KEY';

    /** @var string Environment variable holding the log level. */
    public const ENV_LOG = 'SUQO_LOG';

    /** @var float §4.1 default timeout, in seconds. */
    public const DEFAULT_TIMEOUT = 30.0;

    /** @var int §7.1 */
    public const MAX_RETRIES = 2;

    /** @var int §7.1 milliseconds. */
    public const BASE_DELAY_MS = 500;

    /** @var int §7.1 */
    public const FACTOR = 2;

    /** @var int §7.1 milliseconds. */
    public const MAX_DELAY_MS = 8000;

    /** @var int §7.1 milliseconds. */
    public const RETRY_AFTER_CAP_MS = 60000;

    /**
     * §7.1 / I6 — the single read/write retry decision. Flip here, and only
     * here, when idempotency keys ship. Never exposed as a public option.
     *
     * @var bool
     */
    public const WRITES_RETRYABLE = false;

    /** @var int §12.2 default webhook max age, in seconds. */
    public const WEBHOOK_MAX_AGE = 300;

    /** @var int §12.3 forward skew tolerance, in seconds. Not configurable. */
    public const WEBHOOK_FORWARD_SKEW = 60;

    /** @var string §4.4 MSG_MALFORMED_KEY */
    public const MSG_MALFORMED_KEY = 'Malformed SUQO API key: expected prefix "su_key_" (live) or "su_test_key_" (sandbox).';

    /** @var string §4.4 MSG_NOT_IMPLEMENTED */
    public const MSG_NOT_IMPLEMENTED = 'customers API not yet available in this SDK version';

    /**
     * §7.2 — whether a write may be retried. The decision lives in
     * {@see self::WRITES_RETRYABLE} and is read only through here, so the one
     * place to flip stays the one place to look.
     */
    public static function writesRetryable(): bool
    {
        return self::WRITES_RETRYABLE;
    }

    /**
     * §4.4 MSG_ENV_CONFLICT. {inferred} and {given} interpolate the literal
     * enum strings "live" and "sandbox".
     */
    public static function msgEnvConflict(Environment $inferred, Environment $given): string
    {
        return sprintf(
            'Environment conflict: key prefix implies %s but environment was set to %s.',
            $inferred->value,
            $given->value,
        );
    }

    private function __construct()
    {
    }
}
