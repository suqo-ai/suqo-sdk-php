<?php

declare(strict_types=1);

namespace Suqo\Params;

/**
 * Parameters for `byop.updateCredentials` (openapi:
 * `byop_credentials_partial_update`).
 *
 * The keys depend on the gateway, so they are passed through as given rather
 * than typed — e.g. `['product_code' => 'EPAYTEST', 'merchant_secret' => '…']`
 * for eSewa. The per-gateway field list is in the API reference. Send the full
 * set every time: it replaces what is stored, and unknown keys are refused. A
 * `file` field is a base64 string under 2.5 MB; a `checkbox` field must be `true`.
 */
final class ByopCredentialsParams
{
    /** @param array<string, mixed> $fields Wire names, sent verbatim. */
    public function __construct(
        public readonly array $fields,
    ) {
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        return $this->fields;
    }
}
