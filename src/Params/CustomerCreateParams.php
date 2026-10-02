<?php

declare(strict_types=1);

namespace Suqo\Params;

/**
 * Parameters for `customers.create` (openapi: `ExternalCustomerWrite`).
 *
 * `email` identifies the buyer and is the only required field. An email or phone
 * your account already holds corrects that customer and answers 200 instead of
 * 201, which is what makes the call safe to retry.
 *
 * `phone`, when sent, must be a Nepali mobile number — ten digits starting 96,
 * 97 or 98. A `+977` country code, a leading zero, spaces and dashes are
 * accepted and stripped server-side. It identifies the buyer and cannot be
 * changed afterwards.
 *
 * The name and address are your account's own copy of the buyer's details and
 * are not shared with other sellers. Both read back under the same names;
 * `phone` and `email` read back as `buyerPhone` and `buyerEmail` (§3).
 */
final class CustomerCreateParams
{
    /**
     * @param array<string, mixed> $extra Merged into the request body under wire
     *                                    names, verbatim (N1).
     */
    public function __construct(
        public readonly string $email,
        public readonly ?string $phone = null,
        public readonly ?string $fullName = null,
        public readonly ?string $address = null,
        public readonly array $extra = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        $wire = ['email' => $this->email];

        foreach (['phone' => $this->phone, 'full_name' => $this->fullName, 'address' => $this->address] as $key => $value) {
            if ($value !== null) {
                $wire[$key] = $value;
            }
        }

        return $wire + $this->extra;
    }
}
