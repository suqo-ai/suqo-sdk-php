<?php

declare(strict_types=1);

/**
 * Walk every sub-seller account owned by yours, with its KYC status.
 *
 * SUQO_API_KEY=su_test_key_… php examples/list_sub_sellers.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Suqo\Exception\SuqoError;
use Suqo\SuqoClient;

$suqo = new SuqoClient();

try {
    foreach ($suqo->subSellers->autoPaging(pageSize: 100) as $sub) {
        printf(
            "%s  %-30s  %-24s  %s\n",
            $sub->id ?? '-',
            $sub->email ?? '-',
            $sub->kyc?->status ?? '-',
            $sub->legalBusinessName ?? '-',
        );

        // Set only while the status is KYC_RE_VERIFICATION.
        if ($sub->kyc?->rejectionReason !== null) {
            echo '    rejected: ', $sub->kyc->rejectionReason, PHP_EOL;
        }
    }
} catch (SuqoError $e) {
    fwrite(STDERR, sprintf(
        "suqo error %d (request %s): %s\n",
        $e->status,
        $e->requestId,
        $e->getMessage(),
    ));
    exit(1);
}
