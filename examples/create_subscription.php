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
use Suqo\Exception\ValidationError;
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

    echo 'pbp:      ', $created->pbpId ?? '-', PHP_EOL;
    echo 'return:   ', $created->returnUrl ?? '-', PHP_EOL;
    echo 'customer: ', $created->customer?->email ?? '-', PHP_EOL;

    // Anything the server sends beyond the declared schema is still reachable.
    print_r($created->toArray());
} catch (ValidationError $e) {
    foreach ($e->fieldErrors as $field => $messages) {
        fwrite(STDERR, sprintf("%s: %s\n", $field, implode(', ', $messages)));
    }
    exit(1);
} catch (KycRequiredError $e) {
    fwrite(STDERR, sprintf("KYC %s: %s\n", $e->kycStatus ?? '-', $e->getMessage()));
    exit(1);
} catch (SuqoError $e) {
    fwrite(STDERR, sprintf("suqo error %d: %s\n", $e->status, $e->getMessage()));
    exit(1);
}
