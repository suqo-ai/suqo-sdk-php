<?php

declare(strict_types=1);

/**
 * Lazily walk every customer on the account, then read one back by its public id.
 *
 * A customer record is created implicitly the first time someone subscribes, via
 * `subscriptions->create()`'s `customer` field — this resource is read-only.
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
