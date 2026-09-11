<?php

declare(strict_types=1);

/**
 * Create a subscription for a plan billing point.
 *
 * Note the §3 rename: the SDK says `customer`, the wire says `client`.
 *
 * SUQO_API_KEY=su_test_key_… php examples/create_subscription.php pbp_3n9k2x
 */

require __DIR__ . '/../vendor/autoload.php';

use Suqo\Exception\KycRequiredError;
use Suqo\Exception\SuqoError;
use Suqo\Exception\PermissionDeniedError;
use Suqo\Exception\ValidationError;
use Suqo\Model\SubscriptionStatus;
use Suqo\Params\CreateSubscriptionParams;
use Suqo\Params\CustomerBilling;
use Suqo\Params\CustomerInput;
use Suqo\SuqoClient;

$pbpId = $argv[1] ?? null;

if ($pbpId === null) {
    fwrite(STDERR, "usage: create_subscription.php <pbp_id>\n");
    exit(2);
}

$suqo = new SuqoClient();

try {
    $created = $suqo->subscriptions->create(new CreateSubscriptionParams(
        pbpId: $pbpId,
        customer: new CustomerInput(
            phone: '9841000100',
            fullName: 'Ram Shrestha',
            email: 'ram@client.com',
            address: 'Kathmandu, Nepal',
            billing: new CustomerBilling(
                billingBusinessName: 'ABC Pvt Ltd.',
                billingEmail: 'ram@client.com',
                billingAddress: 'Kathmandu, Nepal',
                billingPanVat: '9841000100',
            ),
        ),
        returnUrl: 'https://merchant.example.com/thanks',
    ));

    echo 'subscription: ', $created->subscriptionId ?? '-', PHP_EOL;
    echo 'pbp:          ', $created->pbpId ?? '-', PHP_EOL;
    echo 'status:       ', $created->status instanceof SubscriptionStatus
        ? $created->status->value
        : ($created->status ?? '-'), PHP_EOL;
    echo 'next billing: ', $created->nextBillingCycle ?? '-', PHP_EOL;
    echo PHP_EOL;

    // The point of the call. Send the buyer here to pay — the 201 is NOT proof
    // of payment; the real outcome arrives on the checkout.succeeded /
    // checkout.failed webhooks. See examples/webhook_handler.php.
    echo 'checkout:     ', $created->checkoutUrl ?? '-', PHP_EOL;
} catch (ValidationError $e) {
    foreach ($e->fieldErrors as $field => $messages) {
        fwrite(STDERR, sprintf("%s: %s\n", $field, implode(', ', $messages)));
    }
    exit(1);
} catch (KycRequiredError $e) {
    fwrite(STDERR, sprintf("KYC %s: %s\n", $e->kycStatus ?? '-', $e->getMessage()));
    exit(1);
} catch (PermissionDeniedError $e) {
    // A 403 that is not KYC-shaped: the key is valid, but this account may not
    // use the endpoint.
    fwrite(STDERR, sprintf("forbidden: %s\n", $e->getMessage()));
    exit(1);
} catch (SuqoError $e) {
    fwrite(STDERR, sprintf("suqo error %d: %s\n", $e->status, $e->getMessage()));
    exit(1);
}
