<?php

declare(strict_types=1);

namespace Suqo\Params;

/**
 * openapi: `ExternalClientBilling`, the `billing` object nested in a create
 * request's `client`. §3's `client` → `customer` rename carries into the stem.
 *
 * Every wire key keeps its `billing_` prefix (N1), even though it is already
 * nested under `billing`.
 *
 * The read side is a different shape with different keys — see
 * {@see \Suqo\Model\SubscriptionCustomerBilling}.
 */
final class CustomerBilling
{
    /**
     * @param array<string, mixed> $extra Merged into the serialised object under
     *        wire names, verbatim (N1).
     */
    public function __construct(
        public readonly string $billingBusinessName,
        public readonly string $billingEmail,
        public readonly string $billingAddress,
        public readonly ?string $billingPanVat = null,
        public readonly array $extra = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        $wire = [
            'billing_business_name' => $this->billingBusinessName,
            'billing_email' => $this->billingEmail,
            'billing_address' => $this->billingAddress,
        ];

        if ($this->billingPanVat !== null) {
            $wire['billing_pan_vat'] = $this->billingPanVat;
        }

        return $wire + $this->extra;
    }

    /**
     * openapi returns this schema back on 201, so the shape reads as well as
     * writes.
     *
     * @param array<string, mixed> $wire
     */
    public static function fromWire(array $wire): self
    {
        return new self(
            is_string($wire['billing_business_name'] ?? null) ? $wire['billing_business_name'] : '',
            is_string($wire['billing_email'] ?? null) ? $wire['billing_email'] : '',
            is_string($wire['billing_address'] ?? null) ? $wire['billing_address'] : '',
            is_string($wire['billing_pan_vat'] ?? null) ? $wire['billing_pan_vat'] : null,
        );
    }
}
