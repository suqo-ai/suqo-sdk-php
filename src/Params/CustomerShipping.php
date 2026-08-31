<?php

declare(strict_types=1);

namespace Suqo\Params;

/**
 * openapi: `ExternalClientShipping`, the `shipping` object nested in a create
 * request's `client`.
 */
final class CustomerShipping
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

        return $wire + $this->extra;
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        return new self(
            is_string($wire['phone'] ?? null) ? $wire['phone'] : '',
            is_string($wire['full_name'] ?? null) ? $wire['full_name'] : '',
            is_string($wire['email'] ?? null) ? $wire['email'] : '',
            is_string($wire['address'] ?? null) ? $wire['address'] : null,
        );
    }
}
