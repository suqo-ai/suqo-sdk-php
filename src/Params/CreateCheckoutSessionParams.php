<?php

declare(strict_types=1);

namespace Suqo\Params;

/**
 * Parameters for `checkoutSessions.create` (openapi: `checkout-sessions_create`).
 *
 * `returnUrl` is required and is where the buyer lands once the session is paid;
 * it is stored and echoed back verbatim.
 *
 * `customerId` is optional. Omitting it — or passing null — lets the buyer
 * identify themselves by OTP at checkout; passing one requires a customer that
 * already exists on your account.
 *
 * B2: parameters are grouped in a params object, constructed with named
 * arguments, so a future optional parameter does not break a call site (§10.4).
 */
final class CreateCheckoutSessionParams
{
    /**
     * @param list<CheckoutItem>   $items      One to ten lines. See {@see CheckoutItem}.
     * @param array<string, mixed> $extra      Merged into the request body under wire
     *                                         names, verbatim (N1).
     */
    public function __construct(
        public readonly array $items,
        public readonly string $returnUrl,
        public readonly ?string $customerId = null,
        public readonly array $extra = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        $wire = [
            'items' => array_map(
                static fn (CheckoutItem $item): array => $item->toWire(),
                array_values($this->items),
            ),
            'return_url' => $this->returnUrl,
        ];

        // A blank customer_id means the same thing as an absent one, but the SDK
        // sends only what the caller set.
        if ($this->customerId !== null) {
            $wire['customer_id'] = $this->customerId;
        }

        return $wire + $this->extra;
    }
}
