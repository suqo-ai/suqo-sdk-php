<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use Suqo\Resource\SubSellers;
use Suqo\SuqoClient;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * Sub-sellers — `list`, `autoPaging`.
 */
final class SubSellersTest extends TransportTestCase
{
    public function testListDecodesThePage(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::page([self::record()]));

        $page = (new SubSellers($this->transport($client)))->list();

        self::assertSame(['usr_8c1d2e3f4', 'Himalayan Tea Pvt. Ltd.'], [$page->results[0]->id, $page->results[0]->legalBusinessName]);
    }

    public function testListHitsItsRouteWithThePageQuery(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::page([]));

        (new SubSellers($this->transport($client)))->list(pageSize: 100);

        self::assertStringContainsString('/api/v1/sub-sellers/?page_size=100', $client->lastRequest()->url);
    }

    public function testDecodesTheNestedKyc(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::page([self::record()]));

        $kyc = (new SubSellers($this->transport($client)))->list()->results[0]->kyc;

        self::assertSame(['KYC_RE_VERIFICATION', 'PAN document is unreadable.'], [$kyc?->status, $kyc?->rejectionReason]);
    }

    public function testAMissingKycReadsAsNull(): void
    {
        $record = self::record();
        unset($record['kyc']);
        $client = (new MockHttpClient())->pushJson(200, self::page([$record]));

        self::assertNull((new SubSellers($this->transport($client)))->list()->results[0]->kyc);
    }

    public function testAutoPagingYieldsEveryRecord(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::page([self::record(), self::record()]));

        self::assertCount(2, iterator_to_array((new SubSellers($this->transport($client)))->autoPaging(), false));
    }

    public function testTheSubSellersResourceIsWiredOntoTheClient(): void
    {
        self::assertInstanceOf(SubSellers::class, (new SuqoClient(apiKey: self::KEY, httpClient: new MockHttpClient()))->subSellers);
    }

    /**
     * @param  list<array<string, mixed>> $results
     * @return array<string, mixed>
     */
    private static function page(array $results): array
    {
        return ['count' => count($results), 'next' => null, 'previous' => null, 'results' => $results];
    }

    /** @return array<string, mixed> */
    private static function record(): array
    {
        return [
            'id' => 'usr_8c1d2e3f4',
            'email' => 'tea@example.com',
            'legal_business_name' => 'Himalayan Tea Pvt. Ltd.',
            'kyc' => [
                'status' => 'KYC_RE_VERIFICATION',
                'rejection_reason' => 'PAN document is unreadable.',
                'resubmitted_at' => null,
                'verified_at' => null,
            ],
            'created_at' => '2026-07-03T10:15:00Z',
        ];
    }
}
