<?php

declare(strict_types=1);

namespace Suqo\Params;

use Suqo\Model\WebhookEvent;

/**
 * Parameters for `webhooks.create` and `webhooks.replace` (openapi:
 * `ExternalWebhook`). Both `event` and `endpointUrl` are required — PUT replaces
 * the whole record; use {@see WebhookUpdateParams} to change one field.
 *
 * An account may hold only one webhook per event. The endpoint must use https
 * and must resolve only to public addresses: every address the name answers with
 * is checked, so a host with both a public and a loopback record is refused.
 * Delivery re-runs the same check, because DNS can be re-pointed after this one
 * passes.
 */
final class WebhookParams
{
    /**
     * @param WebhookEvent|string  $event A {@see WebhookEvent} case, or a raw wire
     *                                    string for an event this SDK does not yet
     *                                    name.
     * @param array<string, mixed> $extra Merged into the request body under wire
     *                                    names, verbatim (N1).
     */
    public function __construct(
        public readonly WebhookEvent|string $event,
        public readonly string $endpointUrl,
        public readonly ?bool $isActive = null,
        public readonly array $extra = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toWire(): array
    {
        $wire = [
            'event' => $this->event instanceof WebhookEvent ? $this->event->value : $this->event,
            'endpoint_url' => $this->endpointUrl,
        ];

        if ($this->isActive !== null) {
            $wire['is_active'] = $this->isActive;
        }

        return $wire + $this->extra;
    }
}
