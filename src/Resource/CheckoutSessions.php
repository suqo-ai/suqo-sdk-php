<?php

declare(strict_types=1);

namespace Suqo\Resource;

use Suqo\Cancellation;
use Suqo\Endpoints;
use Suqo\Model\CheckoutSession;
use Suqo\Model\CheckoutSessionDetail;
use Suqo\Params\CreateCheckoutSessionParams;

/**
 * The checkout sessions resource — openapi's `checkout-sessions_*` operations.
 *
 * A session collects one payment for up to ten of your own billing periods, or
 * for lines you price yourself. It is the path to a payment that is not a
 * subscription; {@see Subscriptions::create()} opens one implicitly for a
 * subscription's first payment, and {@see Subscriptions::renew()} for its next.
 */
final class CheckoutSessions extends AbstractResource
{
    /**
     * POST checkout sessions.
     *
     * This is the one rate-limited endpoint on the partner API: 20 sessions a
     * minute per account by default, answered with a 429 — and so a
     * {@see \Suqo\Exception\RateLimitError} whose `retryAfter` names the wait —
     * once exceeded. It is a write, so the SDK does not retry it for you
     * (§6, I6): back off and resend yourself.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function create(
        CreateCheckoutSessionParams $params,
        ?Cancellation $cancellation = null,
    ): CheckoutSession {
        $response = $this->transport->request(
            'POST',
            Endpoints::CHECKOUT_SESSIONS,
            $params->toWire(),
            [],
            $cancellation,
        );

        return CheckoutSession::fromWire($response->object());
    }

    /**
     * GET one checkout session.
     *
     * Only an *open* session is served. A session that has been paid, or whose
     * `expires_at` has passed, answers 404 exactly as an unknown id does — so a
     * {@see \Suqo\Exception\NotFoundError} here is the ordinary end of a
     * session's life, not necessarily a mistake. When the session existed, that
     * 404 carries its `return_url` beside the message, reachable as
     * `$error->rawBody['return_url']`.
     *
     * @param string $id The `public_id` (`cks_…`) returned when the session was
     *                   opened.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function read(string $id, ?Cancellation $cancellation = null): CheckoutSessionDetail
    {
        $response = $this->transport->request(
            'GET',
            Endpoints::checkoutSessionRead($id),
            null,
            [],
            $cancellation,
        );

        return CheckoutSessionDetail::fromWire($response->object());
    }
}
