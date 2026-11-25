<?php

declare(strict_types=1);

/**
 * Add the bank account payouts are paid to, and verify it.
 *
 * Verification is two runs, because the code is emailed to the seller:
 *
 *   1. With no arguments: list the account, adding a sandbox one if there is
 *      none, and email a verification code for it.
 *   2. With the code: verify the account.
 *
 * SUQO_API_KEY=su_test_key_… php examples/manage_bank_account.php
 * SUQO_API_KEY=su_test_key_… php examples/manage_bank_account.php 123456
 */

require __DIR__ . '/../vendor/autoload.php';

use Suqo\Exception\SuqoError;
use Suqo\Exception\ValidationError;
use Suqo\Params\BankAccountParams;
use Suqo\Params\BankAccountUpdateParams;
use Suqo\SuqoClient;

$code = $argv[1] ?? null;

$suqo = new SuqoClient();

try {
    // Unpaginated: a plain array. One account per seller.
    $account = $suqo->bankAccounts->list()[0] ?? null;

    if ($account === null) {
        $account = $suqo->bankAccounts->create(new BankAccountParams(
            bankName: 'Nabil Bank',
            accountName: 'Himalayan Tea Pvt. Ltd.',
            accountNumber: '0010012345214521',
            branch: 'Kathmandu',
        ));

        echo 'added account ', $account->id, PHP_EOL;
    }

    // The full number while unverified, masked once verified.
    echo $account->bankName, ' ', $account->accountNumberMasked,
         ' verified ', $account->verifiedAt ?? 'no', PHP_EOL;

    if ($code === null) {
        echo $suqo->bankAccounts->sendOtp((int) $account->id)->detail, PHP_EOL;
        echo 'rerun with the emailed code to verify.', PHP_EOL;
        exit(0);
    }

    $account = $suqo->bankAccounts->update((int) $account->id, new BankAccountUpdateParams(code: (int) $code));

    echo 'verified at ', $account->verifiedAt ?? '-', PHP_EOL;
} catch (ValidationError $e) {
    // A wrong or expired code is reported against `code`; a pending payout as
    // code bank_account_has_active_payout.
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
