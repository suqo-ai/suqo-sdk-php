<?php

declare(strict_types=1);

/**
 * Register a webhook, send a test delivery to it, pause it, then clean up.
 *
 * This is webhook *management* — the endpoints SUQO delivers to. Verifying a
 * delivery that has arrived is Suqo\Webhook::verify(), which needs no client at
 * all; see examples/webhook_handler.php.
 *
 * The endpoint must use https and must resolve only to public addresses, so
 * localhost will be refused. Pass a tunnel URL, or any https URL you control.
 *
 * SUQO_API_KEY=su_test_key_… php examples/manage_webhooks.php https://example.com/hooks/suqo
 */

require __DIR__ . '/../vendor/autoload.php';

use Suqo\Exception\SuqoError;
use Suqo\Exception\ValidationError;
use Suqo\Model\WebhookEvent;
use Suqo\Params\WebhookParams;
use Suqo\Params\WebhookUpdateParams;
use Suqo\SuqoClient;

$endpointUrl = $argv[1] ?? null;

if ($endpointUrl === null) {
    fwrite(STDERR, "usage: manage_webhooks.php <https endpoint url>\n");
    exit(2);
}

$suqo = new SuqoClient();

try {
    // The secret every delivery is signed with, minted on first read. Fetch it
    // once at deploy time and keep it in configuration — verification itself
    // must never need the network.
    echo 'signing secret ', $suqo->webhooks->secret()->signingSecret, PHP_EOL, PHP_EOL;

    foreach ($suqo->webhooks->list() as $existing) {
        printf(
            "%-16s %-30s %-40s %s\n",
            $existing->id ?? '-',
            $existing->event instanceof WebhookEvent ? $existing->event->value : ($existing->event ?? '-'),
            $existing->endpointUrl ?? '-',
            ($existing->isActive ?? false) ? 'active' : 'paused',
        );
    }

    // One webhook per event per account, so this 400s if the event is taken.
    $webhook = $suqo->webhooks->create(new WebhookParams(
        event: WebhookEvent::CheckoutSucceeded,
        endpointUrl: $endpointUrl,
    ));

    $id = (string) $webhook->id;

    echo PHP_EOL, 'created ', $id, PHP_EOL;

    // 202 means queued, not delivered: the delivery itself is asynchronous.
    echo $suqo->webhooks->testDelivery($id)->detail, PHP_EOL;

    // Pause deliveries without deleting the subscription: a body of exactly
    // {"is_active": false}.
    $paused = $suqo->webhooks->update($id, new WebhookUpdateParams(isActive: false));

    echo 'paused  ', ($paused->isActive ?? true) ? 'no' : 'yes', PHP_EOL;

    $suqo->webhooks->delete($id);

    echo 'deleted ', $id, PHP_EOL;
} catch (ValidationError $e) {
    // https-only, must resolve, and must resolve only to public addresses —
    // every address the name answers with is checked.
    foreach ($e->fieldErrors as $field => $messages) {
        fwrite(STDERR, sprintf("%s: %s\n", $field, implode(', ', $messages)));
    }

    if ($e->fieldErrors === []) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
    }

    exit(1);
} catch (SuqoError $e) {
    fwrite(STDERR, sprintf(
        "suqo error %d (request %s): %s\n",
        $e->status,
        $e->requestId,
        $e->getMessage(),
    ));
    exit(1);
}
