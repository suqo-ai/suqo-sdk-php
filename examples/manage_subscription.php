<?php

declare(strict_types=1);

/**
 * Read one subscription, schedule its cancellation, undo that, and open a
 * checkout session for its next payment.
 *
 * None of this applies to a one-time purchase: it cannot be cancelled, resumed
 * or rescheduled, and nothing on the subscription record says which kind it is —
 * match its pbpId against the catalogue and read that billing period's
 * intervalType, as this script does.
 *
 * SUQO_API_KEY=su_test_key_… php examples/manage_subscription.php <subscription uuid>
 */

require __DIR__ . '/../vendor/autoload.php';

use Suqo\Exception\NotFoundError;
use Suqo\Exception\SuqoError;
use Suqo\Exception\ValidationError;
use Suqo\IntervalType;
use Suqo\Model\SubscriptionStatus;
use Suqo\SuqoClient;

$subscriptionId = $argv[1] ?? null;

if ($subscriptionId === null) {
    fwrite(STDERR, "usage: manage_subscription.php <subscription uuid>\n");
    exit(2);
}

$suqo = new SuqoClient();

try {
    $subscription = $suqo->subscriptions->read($subscriptionId);

    // status is a SubscriptionStatus case for a value the SDK knows, and the raw
    // wire string for anything the server added since.
    $status = $subscription->status;

    echo 'status   ', $status instanceof SubscriptionStatus ? $status->value : ($status ?? '-'), PHP_EOL;
    echo 'customer ', $subscription->customer?->email ?? '-', PHP_EOL;
    echo 'plan     ', $subscription->product?->pbpId ?? '-',
         ' ', $subscription->product?->price ?? '-',
         ' ', $subscription->product?->currency ?? '', PHP_EOL;
    echo 'next     ', $subscription->nextBillingCycle ?? '-', PHP_EOL;

    // Is it recurring? The subscription does not say; the billing period does.
    $pbpId = $subscription->product?->pbpId;
    $recurring = true;

    foreach ($suqo->products->autoPaging() as $product) {
        foreach ($product->plan as $plan) {
            foreach ($plan->billingPeriods as $period) {
                if ($period->pbpId === $pbpId) {
                    $recurring = IntervalType::parse($period->intervalType) !== IntervalType::OneTime;
                }
            }
        }
    }

    if (!$recurring) {
        echo PHP_EOL, 'one-time purchase: nothing to cancel, resume or renew.', PHP_EOL;
        exit(0);
    }

    // Scheduled for the end of the current period — the subscription stays
    // usable until then, and resume undoes it. Both are safe to repeat.
    echo PHP_EOL, $suqo->subscriptions->cancel($subscriptionId)->message, PHP_EOL;
    echo $suqo->subscriptions->resume($subscriptionId)->message, PHP_EOL;

    // Collect the next payment: a fresh checkout session on the same customer
    // and billing period, so nothing is resubmitted.
    $session = $suqo->subscriptions->renew($subscriptionId);

    echo PHP_EOL, 'renew at ', $session->checkoutUrl ?? '-', PHP_EOL;
    echo 'expires  ', $session->expiresAt ?? '-', PHP_EOL;
} catch (ValidationError $e) {
    // cancel, resume and renew report an illegal state transition as a bare list
    // of messages rather than a field-keyed object, so read getMessage().
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
} catch (NotFoundError $e) {
    fwrite(STDERR, sprintf("no such subscription: %s\n", $e->getMessage()));
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
