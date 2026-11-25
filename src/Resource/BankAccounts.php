<?php

declare(strict_types=1);

namespace Suqo\Resource;

use Suqo\Cancellation;
use Suqo\Endpoints;
use Suqo\Model\BankAccount;
use Suqo\Model\DetailResponse;
use Suqo\Params\BankAccountParams;
use Suqo\Params\BankAccountUpdateParams;

/**
 * The bank accounts resource — openapi's `bank-accounts_*` operations.
 *
 * The account payouts are paid to. One per seller. It starts unverified:
 * {@see self::sendOtp()} emails a code and {@see self::update()} with that code
 * verifies it. Payouts need a verified account.
 */
final class BankAccounts extends AbstractResource
{
    /**
     * GET bank-accounts — every active bank account on your account.
     *
     * Not paginated: the API answers with a bare JSON array.
     *
     * @return list<BankAccount>
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function list(?Cancellation $cancellation = null): array
    {
        $response = $this->transport->request(
            'GET',
            Endpoints::BANK_ACCOUNTS,
            null,
            [],
            $cancellation,
        );

        return array_map(
            static fn (array $record): BankAccount => BankAccount::fromWire($record),
            self::bareList($response->body),
        );
    }

    /**
     * POST bank-accounts — save an account for payouts. It starts unverified.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function create(BankAccountParams $params, ?Cancellation $cancellation = null): BankAccount
    {
        $response = $this->transport->request(
            'POST',
            Endpoints::BANK_ACCOUNTS,
            $params->toWire(),
            [],
            $cancellation,
        );

        return BankAccount::fromWire($response->object());
    }

    /**
     * GET one bank account.
     *
     * @param int $id The integer `id` from a list or create.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function read(int $id, ?Cancellation $cancellation = null): BankAccount
    {
        $response = $this->transport->request(
            'GET',
            Endpoints::bankAccountRead($id),
            null,
            [],
            $cancellation,
        );

        return BankAccount::fromWire($response->object());
    }

    /**
     * PATCH one bank account — verify it with the code from
     * {@see self::sendOtp()}, optionally changing its details in the same call.
     *
     * Every edit needs a fresh code and leaves the account verified. Refused
     * while a payout to this account is pending.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function update(
        int $id,
        BankAccountUpdateParams $params,
        ?Cancellation $cancellation = null,
    ): BankAccount {
        $response = $this->transport->request(
            'PATCH',
            Endpoints::bankAccountRead($id),
            $params->toWire(),
            [],
            $cancellation,
        );

        return BankAccount::fromWire($response->object());
    }

    /**
     * DELETE one bank account. Refused while a payout to it is pending.
     *
     * Answers 204 with no body, so there is nothing to return.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function delete(int $id, ?Cancellation $cancellation = null): void
    {
        $this->transport->request(
            'DELETE',
            Endpoints::bankAccountRead($id),
            null,
            [],
            $cancellation,
        );
    }

    /**
     * POST send-otp — email a 6-digit code, valid for 10 minutes, to the
     * account's verified company or login email (not to the caller). Sending
     * again replaces the previous code. Takes no body.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function sendOtp(int $id, ?Cancellation $cancellation = null): DetailResponse
    {
        $response = $this->transport->request(
            'POST',
            Endpoints::bankAccountSendOtp($id),
            null,
            [],
            $cancellation,
        );

        return DetailResponse::fromWire($response->object());
    }
}
