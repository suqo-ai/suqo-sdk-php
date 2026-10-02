<?php

declare(strict_types=1);

namespace Suqo\Params;

use Suqo\Model\WebhookEvent;

/**
 * Parameters for `webhooks.update` (openapi: `webhooks_partial_update`).
 *
 * Everything is optional and only what you set is sent, so pausing deliveries
 * without deleting the subscription is `new WebhookUpdateParams(isActive: false)`
 * — a body of exactly `{"is_active": false}`.
 *
 * The one-webhook-per-event rule still applies when `event` is changed.
 */
final class WebhookUpdateParams
{
    /**
     * @param array<string, mixed> $extra Merged into the request body under wire
     *                                    names, verbatim (N1).
     */
    public function __construct(
        public readonly WebhookEvent|string|null $event = null,
        public readonly ?string $endpointUrl = null,
        public readonly ?bool $isActive = null,
        public readonly array $extra = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        $wire = [];

        if ($this->event !== null) {
            $wire['event'] = $this->event instanceof WebhookEvent ? $this->event->value : $this->event;
        }

        if ($this->endpointUrl !== null) {
            $wire['endpoint_url'] = $this->endpointUrl;
        }

        if ($this->isActive !== null) {
            $wire['is_active'] = $this->isActive;
        }

        return $wire + $this->extra;
    }
}
