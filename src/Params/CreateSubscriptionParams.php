<?php

declare(strict_types=1);

namespace Suqo\Params;

/**
 * §3 / §10.2 — parameters for `subscriptions.create`. openapi:
 * `ExternalSubscriptionCreate`, whose `*Create` stem reads as an HTTP request
 * object otherwise, so it takes the `*Params` suffix.
 *
 * B2: parameters are grouped in a params object, constructed with named
 * arguments. Adding an optional parameter in a future version therefore does not
 * break existing call sites (§10.4).
 */
final class CreateSubscriptionParams
{
    /**
     * @param string               $pbpId    Required by openapi.
     * @param CustomerInput        $customer Required by openapi (wire `client`).
     * @param string|null          $returnUrl
     * @param array<string, mixed> $extra    Merged into the request body under wire
     *        names, verbatim (N1).
     */
    public function __construct(
        public readonly string $pbpId,
        public readonly CustomerInput $customer,
        public readonly ?string $returnUrl = null,
        public readonly array $extra = [],
    ) {
    }

    /**
     * §3 — the write-direction rename: the surface `customer` field is emitted as
     * `client`.
     *
     * @return array<string, mixed>
     */
    public function toWire(): array
    {
        $wire = [
            'pbp_id' => $this->pbpId,
            'client' => $this->customer->toWire(),
        ];

        if ($this->returnUrl !== null) {
            $wire['return_url'] = $this->returnUrl;
        }

        return $wire + $this->extra;
    }
}
