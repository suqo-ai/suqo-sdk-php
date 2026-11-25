<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use Suqo\Exception\ValidationError;
use Suqo\Resource\Payouts;
use Suqo\SuqoClient;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * Payouts — `list`, `autoPaging`, `create`, `read`.
 */
final class PayoutsTest extends TransportTestCase
{
    public function testListDecodesThePageEnvelope(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['count' => 3, 'next' => null, 'previous' => null, 'results' => [self::record()]]);

        $page = (new Payouts($this->transport($client)))->list(pageSize: 50);

        self::assertSame([3, 'PYT-MF4G9W-CTEP55'], [$page->count, $page->results[0]->payoutId]);
    }

    public function testListSendsThePageQuery(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['count' => 0, 'next' => null, 'previous' => null, 'results' => []]);

        (new Payouts($this->transport($client)))->list(page: 2, pageSize: 50);

        self::assertStringContainsString('/api/v1/payouts/?page=2&page_size=50', $client->lastRequest()->url);
    }

    /** Money stays a string, three decimals intact. */
    public function testAmountsDecodeAsDecimalStrings(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::record());

        $payout = (new Payouts($this->transport($client)))->read('PYT-MF4G9W-CTEP55');

        self::assertSame(
            ['12500.000', '250.000', '375.000', '40.000', '11835.000'],
            [$payout->amount, $payout->providerCharge, $payout->suqoPlatformFee, $payout->smsCharge, $payout->netAmount],
        );
    }

    public function testAPendingPayoutHasNoPaidDateOrReceipt(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::record());

        $payout = (new Payouts($this->transport($client)))->read('PYT-MF4G9W-CTEP55');

        self::assertSame(['PENDING', null, null], [$payout->status, $payout->paidDate, $payout->receiptUrl]);
    }

    public function testReadHitsTheDetailRoute(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::record());

        (new Payouts($this->transport($client)))->read('PYT-MF4G9W-CTEP55');

        self::assertStringEndsWith('/api/v1/payouts/PYT-MF4G9W-CTEP55/', $client->lastRequest()->url);
    }

    /** openapi declares no body, so none is sent. */
    public function testCreatePostsNoBody(): void
    {
        $client = (new MockHttpClient())->pushJson(201, self::record());

        (new Payouts($this->transport($client)))->create();

        self::assertSame(['POST', null], [$client->lastRequest()->method, $client->lastRequest()->body]);
    }

    public function testCreateRefusalCarriesItsCode(): void
    {
        $client = (new MockHttpClient())->pushJson(400, [
            'detail' => 'You already have a pending payout request.',
            'code' => 'pending_payout_request_exists',
        ]);

        try {
            (new Payouts($this->transport($client)))->create();
            self::fail('Expected a ValidationError.');
        } catch (ValidationError $error) {
            self::assertSame(['pending_payout_request_exists'], $error->fieldErrors['code'] ?? null);
        }
    }

    public function testAutoPagingFollowsNext(): void
    {
        $client = (new MockHttpClient())
            ->pushJson(200, ['count' => 2, 'next' => 'https://test-be.suqo.ai/api/v1/payouts/?page=2', 'previous' => null, 'results' => [self::record('PYT-1')]])
            ->pushJson(200, ['count' => 2, 'next' => null, 'previous' => null, 'results' => [self::record('PYT-2')]]);

        $ids = array_map(static fn ($p) => $p->payoutId, iterator_to_array((new Payouts($this->transport($client)))->autoPaging(), false));

        self::assertSame(['PYT-1', 'PYT-2'], $ids);
    }

    public function testThePayoutsResourceIsWiredOntoTheClient(): void
    {
        self::assertInstanceOf(Payouts::class, (new SuqoClient(apiKey: self::KEY, httpClient: new MockHttpClient()))->payouts);
    }

    /** @return array<string, mixed> */
    private static function record(string $id = 'PYT-MF4G9W-CTEP55'): array
    {
        return [
            'payout_id' => $id,
            'status' => 'PENDING',
            'amount' => '12500.000',
            'provider_charge' => '250.000',
            'suqo_platform_fee' => '375.000',
            'sms_charge' => '40.000',
            'net_amount' => '11835.000',
            'requested_date' => '2026-07-03T10:15:00Z',
            'paid_date' => null,
            'receipt_url' => null,
        ];
    }
}
