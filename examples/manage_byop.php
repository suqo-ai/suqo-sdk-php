<?php

declare(strict_types=1);

/**
 * List your payment methods with their bring-your-own-payment state, and
 * optionally set the processing fee your own gateway charges on one.
 *
 * The fee write answers 403 until SUQO has switched the account to BYOP.
 *
 * SUQO_API_KEY=su_test_key_… php examples/manage_byop.php
 * SUQO_API_KEY=su_test_key_… php examples/manage_byop.php <payment_type_id> 1.75
 */

require __DIR__ . '/../vendor/autoload.php';

use Suqo\Exception\PermissionDeniedError;
use Suqo\Exception\SuqoError;
use Suqo\Params\ByopFeeParams;
use Suqo\SuqoClient;

$paymentTypeId = $argv[1] ?? null;
$fee = $argv[2] ?? null;

$suqo = new SuqoClient();

try {
    foreach ($suqo->byop->paymentMethods() as $method) {
        // isVerified is tri-state: null means the background check has not run.
        $verified = $method->isVerified === null ? 'unchecked' : ($method->isVerified ? 'yes' : 'no');

        printf(
            "%s  %-10s  active %-3s  configured %-3s  verified %-9s  fee %s\n",
            $method->paymentTypeId ?? '-',
            $method->paymentType ?? '-',
            $method->isActive ? 'yes' : 'no',
            $method->isConfigured ? 'yes' : 'no',
            $verified,
            $method->fee ?? '-',
        );
    }

    if ($paymentTypeId !== null && $fee !== null) {
        // A decimal string, never a float. ByopFeeParams(null) would clear it.
        $saved = $suqo->byop->updateFee($paymentTypeId, new ByopFeeParams($fee));

        echo PHP_EOL, 'processing fee now ', $saved->processingFee ?? 'cleared', PHP_EOL;
    }
} catch (PermissionDeniedError $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
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
