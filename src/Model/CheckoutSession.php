<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * The 201 of `checkout-sessions_create`, and of `subscription_renew_create`,
 * which opens a session for the next payment on an existing subscription.
 *
 * `checkoutUrl` is the whole point: send it to the buyer to collect payment.
 * The session stops being servable once it is paid or `expiresAt` passes.
 *
 * Returned by {@see \Suqo\Resource\CheckoutSessions::create()} and
 * {@see \Suqo\Resource\Subscriptions::renew()}.
 */
final class CheckoutSession extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $publicId,
        public readonly ?string $expiresAt,
        public readonly ?string $checkoutUrl,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::nstr($wire, 'public_id'),
            Wire::nstr($wire, 'expires_at'),
            Wire::nstr($wire, 'checkout_url'),
            $wire,
        );
    }
}
