<?php

declare(strict_types=1);

/**
 * Open a checkout session, print the URL to send the buyer to, then read the
 * session back while it is still open.
 *
 * A session collects one payment for up to ten billing periods, or for lines you
 * price yourself. An item is either a `pbp_id` or an inline line, never both —
 * which is why `CheckoutItem` has one named constructor per shape.
 *
 * Run examples/list_products.php first: that is where a pbp_id comes from.
 *
 * SUQO_API_KEY=su_test_key_… php examples/checkout_session.php pbp_3n9k2x
 */

require __DIR__ . '/../vendor/autoload.php';

use Suqo\Exception\NotFoundError;
use Suqo\Exception\RateLimitError;
use Suqo\Exception\SuqoError;
use Suqo\Exception\ValidationError;
use Suqo\IntervalType;
use Suqo\Params\CheckoutItem;
use Suqo\Params\CreateCheckoutSessionParams;
use Suqo\SuqoClient;

$pbpId = $argv[1] ?? null;

if ($pbpId === null) {
    fwrite(STDERR, "usage: checkout_session.php <pbp_id>\n");
    exit(2);
}

$suqo = new SuqoClient();

try {
    $session = $suqo->checkoutSessions->create(new CreateCheckoutSessionParams(
        items: [
            CheckoutItem::billingPeriod($pbpId),
            CheckoutItem::inline(
                name: 'Setup fee',
                amount: '2500.00',              // a decimal string, never a float
                intervalType: IntervalType::OneTime,
                intervalCount: 0,               // must be 0 for one_time
                discountAmount: '500.00',
            ),
        ],
        returnUrl: 'https://merchant.example.com/orders/1234',
        // customerId: 'cus_…' — omitted, so the buyer identifies by OTP at checkout.
    ));

    echo 'session  ', $session->publicId ?? '-', PHP_EOL;
    echo 'expires  ', $session->expiresAt ?? '-', PHP_EOL;
    echo 'pay at   ', $session->checkoutUrl ?? '-', PHP_EOL;

    // Reading it back works only while the session is open. Once it is paid or
    // expired this is a 404 — which is the ordinary end of a session's life.
    $detail = $suqo->checkoutSessions->read((string) $session->publicId);

    echo PHP_EOL;
    echo 'seller   ', $detail->sellerDetails?->businessName ?? '-', PHP_EOL;
    echo 'customer ', $detail->customerId ?? '(OTP at checkout)', PHP_EOL;
    echo 'items    ', count($detail->lineItems), PHP_EOL;
    echo 'return   ', $detail->returnUrl ?? '-', PHP_EOL;
} catch (ValidationError $e) {
    foreach ($e->fieldErrors as $field => $messages) {
        fwrite(STDERR, sprintf("%s: %s\n", $field, implode(', ', $messages)));
    }

    if ($e->fieldErrors === []) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
    }

    exit(1);
} catch (RateLimitError $e) {
    // The one rate-limited endpoint: 20 sessions a minute per account. It is a
    // write, so the SDK does not retry it for you.
    fwrite(STDERR, sprintf("rate limited; retry in %.0fs\n", $e->retryAfter ?? 1.0));
    exit(1);
} catch (NotFoundError $e) {
    $returnUrl = is_array($e->rawBody) ? ($e->rawBody['return_url'] ?? '') : '';

    fwrite(STDERR, sprintf("session no longer open: %s\n", $e->getMessage()));

    if (is_string($returnUrl) && $returnUrl !== '') {
        fwrite(STDERR, sprintf("send the buyer back to: %s\n", $returnUrl));
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
