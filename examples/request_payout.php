<?php

declare(strict_types=1);

/**
 * Request a payout of everything currently due, then list past payouts.
 *
 * A payout needs a verified bank account (see manage_bank_account.php). Only one
 * can be pending at a time, so running this twice reports the pending one rather
 * than paying twice.
 *
 * SUQO_API_KEY=su_test_key_… php examples/request_payout.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Suqo\Exception\SuqoError;
use Suqo\Exception\ValidationError;
use Suqo\SuqoClient;

$suqo = new SuqoClient();

try {
    try {
        $payout = $suqo->payouts->create();

        echo 'requested ', $payout->payoutId, ' net ', $payout->netAmount, PHP_EOL;
    } catch (ValidationError $e) {
        // The refusal carries a code naming the reason: pending_payout_request_exists,
        // bank_account_not_found, bank_account_not_verified, no_transactions_due,
        // payout_below_minimum.
        echo 'not requested: ', $e->fieldErrors['code'][0] ?? '-', ' — ', $e->getMessage(), PHP_EOL;
    }

    echo PHP_EOL;

    foreach ($suqo->payouts->autoPaging() as $payout) {
        // Amounts are decimal strings, up to three places; never floats.
        printf(
            "%s  %-9s  gross %s  net %s  paid %s\n",
            $payout->payoutId ?? '-',
            $payout->status ?? '-',
            $payout->amount ?? '-',
            $payout->netAmount ?? '-',
            $payout->paidDate ?? '-',
        );
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
