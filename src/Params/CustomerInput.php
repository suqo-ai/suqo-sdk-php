<?php

declare(strict_types=1);

namespace Suqo\Params;

/**
 * §3 — the surface name for openapi's `ExternalClient`: the write shape of a
 * buyer. Serialises to the object that sits under the `client` key of a create
 * request; the rename itself lives in {@see CreateSubscriptionParams::toWire()}.
 *
 * openapi uses this same schema for the 201 response body, so the type reads as
 * well as writes — see {@see \Suqo\Model\CreateSubscriptionResponse}.
 */
final class CustomerInput
{
    /**
     * @param array<string, mixed> $extra Merged into the serialised object under
     *        wire names, verbatim (N1).
     */
    public function __construct(
        public readonly string $phone,
        public readonly string $fullName,
        public readonly string $email,
        public readonly ?string $address = null,
        public readonly ?CustomerBilling $billing = null,
        public readonly ?CustomerShipping $shipping = null,
        public readonly array $extra = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        $wire = [
            'phone' => $this->phone,
            'full_name' => $this->fullName,
            'email' => $this->email,
        ];

        if ($this->address !== null) {
            $wire['address'] = $this->address;
        }
        if ($this->billing !== null) {
            $wire['billing'] = $this->billing->toWire();
        }
        if ($this->shipping !== null) {
            $wire['shipping'] = $this->shipping->toWire();
        }

        return $wire + $this->extra;
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        $billing = $wire['billing'] ?? null;
        $shipping = $wire['shipping'] ?? null;

        return new self(
            is_string($wire['phone'] ?? null) ? $wire['phone'] : '',
            is_string($wire['full_name'] ?? null) ? $wire['full_name'] : '',
            is_string($wire['email'] ?? null) ? $wire['email'] : '',
            is_string($wire['address'] ?? null) ? $wire['address'] : null,
            self::isWireObject($billing) ? CustomerBilling::fromWire($billing) : null,
            self::isWireObject($shipping) ? CustomerShipping::fromWire($shipping) : null,
        );
    }

    /**
     * @phpstan-assert-if-true array<string, mixed> $value
     */
    private static function isWireObject(mixed $value): bool
    {
        return is_array($value) && ($value === [] || !array_is_list($value));
    }
}
