<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * openapi: `CheckoutSessionDetail` — one open checkout session, read back by its
 * `public_id`.
 *
 * A session is served only while it is open: once it is paid, or once
 * `expiresAt` has passed, the endpoint answers 404 rather than returning the
 * record, and so does an unknown id or one belonging to another account. That
 * 404 carries the session's own `return_url` beside its `detail` when the
 * session existed, reachable through `$error->rawBody`.
 *
 * `lineItems` is kept exactly as it arrived — openapi declares the container
 * without describing its members, so there is nothing to type.
 *
 * Returned by {@see \Suqo\Resource\CheckoutSessions::read()}.
 */
final class CheckoutSessionDetail extends Model
{
    /**
     * @param array<mixed>         $lineItems
     * @param array<string, mixed> $wire
     */
    private function __construct(
        public readonly ?string $publicId,
        public readonly array $lineItems,
        public readonly ?string $customerId,
        public readonly ?CheckoutSeller $sellerDetails,
        public readonly ?string $returnUrl,
        public readonly ?string $expiresAt,
        public readonly ?string $completedAt,
        public readonly ?bool $isExpired,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        $seller = Wire::object($wire, 'seller_details');

        return new self(
            Wire::nstr($wire, 'public_id'),
            Wire::arr($wire, 'line_items'),
            Wire::nstr($wire, 'customer_id'),
            $seller === null ? null : CheckoutSeller::fromWire($seller),
            Wire::nstr($wire, 'return_url'),
            Wire::nstr($wire, 'expires_at'),
            Wire::nstr($wire, 'completed_at'),
            // openapi types `is_expired` as a string, so the strict reader would
            // drop it; nflexbool accepts both spellings.
            Wire::nflexbool($wire, 'is_expired'),
            $wire,
        );
    }
}
