<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use Suqo\Params\BankAccountParams;
use Suqo\Params\BankAccountUpdateParams;
use Suqo\Resource\BankAccounts;
use Suqo\SuqoClient;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * Bank accounts — `list`, `create`, `read`, `update`, `delete`, `sendOtp`.
 */
final class BankAccountsTest extends TransportTestCase
{
    /** Unpaginated: a bare JSON array. */
    public function testListDecodesABareArray(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [self::record()]);

        $accounts = (new BankAccounts($this->transport($client)))->list();

        self::assertSame([42, 'Nabil Bank'], [$accounts[0]->id, $accounts[0]->bankName]);
    }

    public function testListToleratesAnUnexpectedBody(): void
    {
        $client = (new MockHttpClient())->pushJson(200, null);

        self::assertSame([], (new BankAccounts($this->transport($client)))->list());
    }

    public function testCreateSendsEveryField(): void
    {
        $client = (new MockHttpClient())->pushJson(201, self::record());

        (new BankAccounts($this->transport($client)))->create(new BankAccountParams(
            bankName: 'Nabil Bank',
            accountName: 'Himalayan Tea Pvt. Ltd.',
            accountNumber: '0010012345214521',
            branch: 'Kathmandu',
        ));

        self::assertSame(
            ['bank_name' => 'Nabil Bank', 'account_name' => 'Himalayan Tea Pvt. Ltd.', 'account_number' => '0010012345214521', 'branch' => 'Kathmandu'],
            self::decodeBody($client),
        );
    }

    public function testReadPutsTheIntegerIdInThePath(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::record());

        (new BankAccounts($this->transport($client)))->read(42);

        self::assertStringEndsWith('/api/v1/bank-accounts/42/', $client->lastRequest()->url);
    }

    public function testDecodesTheRecord(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::record());

        $account = (new BankAccounts($this->transport($client)))->read(42);

        self::assertSame(['****4521', '2026-07-03T10:15:00Z'], [$account->accountNumberMasked, $account->verifiedAt]);
    }

    /** Verifying without an edit sends the code alone. */
    public function testUpdateSendsOnlyTheCodeAndWhatWasSet(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::record());

        (new BankAccounts($this->transport($client)))->update(42, new BankAccountUpdateParams(code: 123456, branch: 'Lalitpur'));

        self::assertSame(['PATCH', ['code' => 123456, 'branch' => 'Lalitpur']], [$client->lastRequest()->method, self::decodeBody($client)]);
    }

    public function testDeleteToleratesAnEmpty204(): void
    {
        $client = (new MockHttpClient())->pushJson(204, null);

        (new BankAccounts($this->transport($client)))->delete(42);

        self::assertSame('DELETE', $client->lastRequest()->method);
    }

    public function testSendOtpPostsNoBodyToItsRoute(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['detail' => 'Verification code sent.']);

        (new BankAccounts($this->transport($client)))->sendOtp(42);

        self::assertSame(
            [null, true],
            [$client->lastRequest()->body, str_ends_with($client->lastRequest()->url, '/api/v1/bank-accounts/42/send-otp/')],
        );
    }

    public function testSendOtpReadsTheDetail(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['detail' => 'Verification code sent.']);

        self::assertSame('Verification code sent.', (new BankAccounts($this->transport($client)))->sendOtp(42)->detail);
    }

    public function testTheBankAccountsResourceIsWiredOntoTheClient(): void
    {
        self::assertInstanceOf(BankAccounts::class, (new SuqoClient(apiKey: self::KEY, httpClient: new MockHttpClient()))->bankAccounts);
    }

    /** @return array<string, mixed> */
    private static function record(): array
    {
        return [
            'id' => 42,
            'bank_name' => 'Nabil Bank',
            'account_name' => 'Himalayan Tea Pvt. Ltd.',
            'branch' => 'Kathmandu',
            'account_number_masked' => '****4521',
            'verified_at' => '2026-07-03T10:15:00Z',
            'created_at' => '2026-07-03T10:05:00Z',
            'updated_at' => '2026-07-03T10:15:00Z',
        ];
    }

    private static function decodeBody(MockHttpClient $client): mixed
    {
        return json_decode((string) $client->lastRequest()->body, true);
    }
}
