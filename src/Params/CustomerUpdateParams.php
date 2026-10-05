<?php

declare(strict_types=1);

namespace Suqo\Params;

/**
 * Parameters for `customers.update` (openapi: `customers_partial_update`).
 *
 * Every field is optional and only what you set is sent, so this is a true
 * PATCH. **Null and `''` are different instructions**: null leaves the field
 * alone, `''` clears it.
 *
 * `phone` identifies the buyer and cannot be changed — openapi accepts it only
 * at its current value and answers `{"phone": ["Phone cannot be changed."]}`
 * otherwise. It is here so that a round trip through
 * {@see \Suqo\Model\Customer::toArray()} can resend it unchanged; leave it null
 * in ordinary use.
 *
 * `email` reads back as `buyerEmail` (§3).
 */
final class CustomerUpdateParams
{
    /**
     * @param array<string, mixed> $extra Merged into the request body under wire
     *                                    names, verbatim (N1).
     */
    public function __construct(
        public readonly ?string $fullName = null,
        public readonly ?string $email = null,
        public readonly ?string $address = null,
        public readonly ?string $phone = null,
        public readonly array $extra = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        $wire = [];

        foreach ([
            'full_name' => $this->fullName,
            'email' => $this->email,
            'address' => $this->address,
            'phone' => $this->phone,
        ] as $key => $value) {
            if ($value !== null) {
                $wire[$key] = $value;
            }
        }

        return $wire + $this->extra;
    }
}
