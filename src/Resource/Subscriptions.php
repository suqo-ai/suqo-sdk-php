<?php

declare(strict_types=1);

namespace Suqo\Resource;

use Generator;
use Suqo\Cancellation;
use Suqo\Endpoints;
use Suqo\Model\CreateSubscriptionResponse;
use Suqo\Model\MessageResponse;
use Suqo\Model\Subscription;
use Suqo\Model\SubscriptionPage;
use Suqo\Pagination;
use Suqo\Params\CreateSubscriptionParams;
use Suqo\Params\UpdateBillingCycleParams;

/**
 * §10.2 — the subscriptions resource.
 */
final class Subscriptions extends AbstractResource
{
    /**
     * §10.2 — GET subscriptions. Applies the §3 read-direction rename to every
     * record.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function list(
        ?int $page = null,
        ?int $pageSize = null,
        ?Cancellation $cancellation = null,
    ): SubscriptionPage {
        $response = $this->transport->request(
            'GET',
            Endpoints::SUBSCRIPTIONS,
            null,
            self::pageQuery($page, $pageSize),
            $cancellation,
        );

        return SubscriptionPage::fromSubscriptionsWire($response->object());
    }

    /**
     * §10.2 — a lazy sequence of subscriptions across every page. The read-direction
     * rename applies to records yielded lazily exactly as it does to page 1.
     *
     * @return Generator<int, Subscription>
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function autoPaging(
        ?int $page = null,
        ?int $pageSize = null,
        ?Cancellation $cancellation = null,
    ): Generator {
        return Pagination::autoPage(
            $this->transport,
            $this->transport->url(Endpoints::SUBSCRIPTIONS, self::pageQuery($page, $pageSize)),
            static fn (array $record): Subscription => Subscription::fromWire($record),
            $cancellation,
        );
    }

    /**
     * §10.2 — POST subscriptions. Applies the §3 write-direction rename: the
     * surface `customer` field is emitted as `client`.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function create(
        CreateSubscriptionParams $params,
        ?Cancellation $cancellation = null,
    ): CreateSubscriptionResponse {
        $response = $this->transport->request(
            'POST',
            Endpoints::SUBSCRIPTIONS,
            $params->toWire(),
            [],
            $cancellation,
        );

        return CreateSubscriptionResponse::fromWire($response->object());
    }

    /**
     * §10.2 — POST subscription cancel. openapi declares no request body, so none
     * is sent and no Content-Type header is set (§6.3).
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function cancel(string $id, ?Cancellation $cancellation = null): MessageResponse
    {
        $response = $this->transport->request(
            'POST',
            Endpoints::subscriptionCancel($id),
            null,
            [],
            $cancellation,
        );

        return MessageResponse::fromWire($response->object());
    }

    /**
     * §10.2 — POST subscription billing cycle.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function updateBillingCycle(
        UpdateBillingCycleParams $params,
        ?Cancellation $cancellation = null,
    ): MessageResponse {
        $response = $this->transport->request(
            'POST',
            Endpoints::SUBSCRIPTION_BILLING_CYCLE,
            $params->toWire(),
            [],
            $cancellation,
        );

        return MessageResponse::fromWire($response->object());
    }
}
