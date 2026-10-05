<?php

declare(strict_types=1);

namespace Suqo\Resource;

use Generator;
use Suqo\Cancellation;
use Suqo\Endpoints;
use Suqo\Model\CheckoutSession;
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

    /**
     * §10.2 — GET one subscription.
     *
     * Nothing on the record says whether it is recurring. Match
     * `$subscription->product->pbpId` against `products->list()` and read that
     * billing period's `interval_type`: `one_time` means the subscription can be
     * neither cancelled, resumed nor rescheduled.
     *
     * @param string $id The subscription UUID.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function read(string $id, ?Cancellation $cancellation = null): Subscription
    {
        $response = $this->transport->request(
            'GET',
            Endpoints::subscriptionRead($id),
            null,
            [],
            $cancellation,
        );

        return Subscription::fromWire($response->object());
    }

    /**
     * §10.2 — POST subscription resume. Undoes a scheduled cancellation and
     * returns the subscription to active.
     *
     * An already-active subscription is returned unchanged, so this is safe to
     * retry. A subscription that is already fully cancelled cannot be resumed —
     * that answers 400 with a bare list of messages, which surfaces as the
     * message on a {@see \Suqo\Exception\ValidationError}. openapi declares no
     * request body, so none is sent and no Content-Type header is set (§6.3).
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function resume(string $id, ?Cancellation $cancellation = null): MessageResponse
    {
        $response = $this->transport->request(
            'POST',
            Endpoints::subscriptionResume($id),
            null,
            [],
            $cancellation,
        );

        return MessageResponse::fromWire($response->object());
    }

    /**
     * §10.2 — POST subscription renew. Opens a checkout session for the next
     * payment on one of your own subscriptions, reusing its existing customer
     * and billing period rather than resubmitting them.
     *
     * Send the returned `checkoutUrl` to the buyer to collect payment. A
     * cancelled or archived subscription, and a paid one-time purchase, cannot
     * be renewed.
     *
     * Note the route is the singular `/subscription/renew/`, and that the
     * subscription is named in the body rather than the path — so this takes the
     * id as a plain string, like {@see self::cancel()}, and not a params object.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function renew(string $subscriptionId, ?Cancellation $cancellation = null): CheckoutSession
    {
        $response = $this->transport->request(
            'POST',
            Endpoints::SUBSCRIPTION_RENEW,
            ['subscription_id' => $subscriptionId],
            [],
            $cancellation,
        );

        return CheckoutSession::fromWire($response->object());
    }
}
