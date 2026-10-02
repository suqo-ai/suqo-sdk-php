<?php

declare(strict_types=1);

/**
 * Lazily walk every customer on the account, read one back by its public id, then
 * record and correct one.
 *
 * A customer record is also created implicitly the first time someone subscribes,
 * via `subscriptions->create()`'s `customer` field; `create()` records one
 * without opening a subscription, and is an upsert — an email or phone the
 * account already holds is corrected and answered 200 rather than 201.
 *
 * Note that `id` is a prefixed public id (`cus_0390b1820`), not an integer and
 * not a UUID, and that every field except the id, phone and timestamp can be
 * null.
 *
 * SUQO_API_KEY=su_test_key_… php examples/list_customers.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Suqo\Exception\NotFoundError;
use Suqo\Exception\SuqoError;
use Suqo\Exception\ValidationError;
use Suqo\Params\CustomerCreateParams;
use Suqo\Params\CustomerUpdateParams;
use Suqo\SuqoClient;

$suqo = new SuqoClient();

try {
    $first = null;

    foreach ($suqo->customers->autoPaging(pageSize: 50) as $customer) {
        $first ??= $customer->id;

        printf(
            "%-16s %-12s %-28s %-20s %s\n",
            $customer->id ?? '-',
            $customer->buyerPhone ?? '-',
            $customer->buyerEmail ?? '-',
            $customer->fullName ?? '-',
            $customer->address ?? '-',
        );
    }

    if ($first === null) {
        echo 'No customers on this account yet.', PHP_EOL;
        exit(0);
    }

    // The same record, fetched on its own.
    $one = $suqo->customers->read($first);

    echo PHP_EOL, 'read ', $one->id ?? '-', ' created ', $one->createdAt ?? '-', PHP_EOL;

    // Record a customer of our own. Run this twice: the second run corrects the
    // same record and answers 200 instead of 201, which is what makes a retry
    // after a timeout safe.
    $recorded = $suqo->customers->create(new CustomerCreateParams(
        email: 'ram@example.com',
        phone: '9810000001',            // Nepali mobile; +977 accepted and stripped
        fullName: 'Ram Bahadur',
        address: 'Kathmandu, Nepal',
    ));

    echo PHP_EOL, 'recorded ', $recorded->id ?? '-', ' ', $recorded->buyerEmail ?? '-', PHP_EOL;

    // Only what you set is sent. null leaves a field alone; '' clears it. The
    // phone identifies the buyer and cannot be changed.
    $corrected = $suqo->customers->update((string) $recorded->id, new CustomerUpdateParams(
        fullName: 'Ram Bahadur Thapa',
        address: '',
    ));

    echo 'corrected ', $corrected->fullName ?? '-', ' address ',
         ($corrected->address ?? '') === '' ? '(cleared)' : $corrected->address, PHP_EOL;
} catch (ValidationError $e) {
    foreach ($e->fieldErrors as $field => $messages) {
        fwrite(STDERR, sprintf("%s: %s\n", $field, implode(', ', $messages)));
    }

    exit(1);
} catch (NotFoundError $e) {
    fwrite(STDERR, sprintf("no such customer: %s\n", $e->getMessage()));
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
