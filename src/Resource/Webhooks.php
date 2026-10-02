<?php

declare(strict_types=1);

namespace Suqo\Resource;

use Suqo\Cancellation;
use Suqo\Endpoints;
use Suqo\Model\DetailResponse;
use Suqo\Model\SigningSecret;
use Suqo\Model\WebhookEndpoint;
use Suqo\Params\WebhookParams;
use Suqo\Params\WebhookUpdateParams;

/**
 * The webhooks management resource — openapi's `webhooks_*` operations.
 *
 * This registers and edits the endpoints SUQO delivers to. It is unrelated to
 * {@see \Suqo\Webhook::verify()}, which verifies an inbound delivery once it
 * arrives and needs no client, no API key and no network. The two meet at
 * {@see self::secret()}: the secret it returns is the one `verify()` takes.
 */
final class Webhooks extends AbstractResource
{
    /**
     * GET webhooks — every webhook registered by your account.
     *
     * The only collection on the API that is not paginated: it answers with a
     * bare JSON array, so there is no `Page` here and nothing to auto-page.
     *
     * @return list<WebhookEndpoint>
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function list(?Cancellation $cancellation = null): array
    {
        $response = $this->transport->request(
            'GET',
            Endpoints::WEBHOOKS,
            null,
            [],
            $cancellation,
        );

        $body = $response->body;

        if (!is_array($body)) {
            return [];
        }

        $out = [];

        foreach ($body as $record) {
            if (is_array($record) && ($record === [] || !array_is_list($record))) {
                /** @var array<string, mixed> $record */
                $out[] = WebhookEndpoint::fromWire($record);
            }
        }

        return $out;
    }

    /**
     * POST webhooks — subscribe an endpoint to one event.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function create(WebhookParams $params, ?Cancellation $cancellation = null): WebhookEndpoint
    {
        $response = $this->transport->request(
            'POST',
            Endpoints::WEBHOOKS,
            $params->toWire(),
            [],
            $cancellation,
        );

        return WebhookEndpoint::fromWire($response->object());
    }

    /**
     * GET one webhook.
     *
     * @param string $id The public id (`whk_…`) from the `id` field of a list or
     *                   create.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function read(string $id, ?Cancellation $cancellation = null): WebhookEndpoint
    {
        $response = $this->transport->request(
            'GET',
            Endpoints::webhookRead($id),
            null,
            [],
            $cancellation,
        );

        return WebhookEndpoint::fromWire($response->object());
    }

    /**
     * PUT one webhook — overwrite its event, endpoint and active flag.
     *
     * Named `replace` rather than `update` because that is what PUT does here:
     * both `event` and `endpointUrl` are required, and a field you leave out of
     * {@see WebhookParams} is not preserved. {@see self::update()} is the
     * one-field change.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function replace(
        string $id,
        WebhookParams $params,
        ?Cancellation $cancellation = null,
    ): WebhookEndpoint {
        $response = $this->transport->request(
            'PUT',
            Endpoints::webhookRead($id),
            $params->toWire(),
            [],
            $cancellation,
        );

        return WebhookEndpoint::fromWire($response->object());
    }

    /**
     * PATCH one webhook — change only the fields you set.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function update(
        string $id,
        WebhookUpdateParams $params,
        ?Cancellation $cancellation = null,
    ): WebhookEndpoint {
        $response = $this->transport->request(
            'PATCH',
            Endpoints::webhookRead($id),
            $params->toWire(),
            [],
            $cancellation,
        );

        return WebhookEndpoint::fromWire($response->object());
    }

    /**
     * DELETE one webhook. Past delivery records are kept for debugging.
     *
     * Answers 204 with no body, so there is nothing to return.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function delete(string $id, ?Cancellation $cancellation = null): void
    {
        $this->transport->request(
            'DELETE',
            Endpoints::webhookRead($id),
            null,
            [],
            $cancellation,
        );
    }

    /**
     * GET the account's webhook signing secret, minted on first read.
     *
     * Every delivery is signed with it; it is not rotatable through this API.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function secret(?Cancellation $cancellation = null): SigningSecret
    {
        $response = $this->transport->request(
            'GET',
            Endpoints::WEBHOOK_SECRET,
            null,
            [],
            $cancellation,
        );

        return SigningSecret::fromWire($response->object());
    }

    /**
     * POST a test delivery — enqueue a signed payload so you can confirm your
     * receiver.
     *
     * Returns as soon as the job is queued (202). The delivery itself is
     * asynchronous, so this answering successfully says nothing about whether
     * your endpoint accepted it.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function testDelivery(string $id, ?Cancellation $cancellation = null): DetailResponse
    {
        $response = $this->transport->request(
            'POST',
            Endpoints::webhookTestDelivery($id),
            null,
            [],
            $cancellation,
        );

        return DetailResponse::fromWire($response->object());
    }
}
