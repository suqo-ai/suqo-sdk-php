<?php

declare(strict_types=1);

namespace Suqo\Model;

use Suqo\Params\CustomerInput;

/**
 * §10.2 — the response to `subscriptions.create`.
 *
 * openapi declares the 201 body as `ExternalSubscriptionCreate`, the same schema
 * as the request, so the response echoes what was sent. `customer` is therefore
 * the write shape ({@see CustomerInput}) read back, not the embedded read shape
 * that a subscription record carries.
 *
 * Anything the server adds beyond the declared schema — a checkout URL, say — is
 * reachable through {@see Model::toArray()} without an SDK change.
 */
final class CreateSubscriptionResponse extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $pbpId,
        public readonly ?string $returnUrl,
        public readonly ?CustomerInput $customer,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        $client = Wire::object($wire, 'client');

        return new self(
            Wire::nstr($wire, 'pbp_id'),
            Wire::nstr($wire, 'return_url'),
            $client === null ? null : CustomerInput::fromWire($client),
            $wire,
        );
    }
}
