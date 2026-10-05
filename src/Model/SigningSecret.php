<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * The account's webhook signing secret, minted on first read.
 *
 * Feed `signingSecret` to {@see \Suqo\Webhook::verify()} — it is the secret every
 * delivery is signed with. It is not rotatable through the API, so treat it like
 * any other credential: store it, do not log it.
 *
 * Returned by {@see \Suqo\Resource\Webhooks::secret()}.
 */
final class SigningSecret extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly string $signingSecret,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(Wire::str($wire, 'signing_secret'), $wire);
    }
}
