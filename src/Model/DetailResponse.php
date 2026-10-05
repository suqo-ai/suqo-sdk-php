<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * An acknowledgement whose single field is `detail` rather than `message` —
 * which is why it is not {@see MessageResponse}.
 *
 * Returned by {@see \Suqo\Resource\Webhooks::testDelivery()}, whose 202 says the
 * delivery was enqueued. The delivery itself is asynchronous: a 202 is not
 * evidence that your endpoint answered.
 */
final class DetailResponse extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly string $detail,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(Wire::str($wire, 'detail'), $wire);
    }
}
