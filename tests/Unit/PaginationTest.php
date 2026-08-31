<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use Suqo\Cancellation;
use Suqo\Exception\CancelledError;
use Suqo\Model\Product;
use Suqo\Resource\Products;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * §13 — Pagination, P1..P5.
 */
final class PaginationTest extends TransportTestCase
{
    private const PAGE_2 = 'https://test-be.suqo.ai/api/v1/products/?page=2';
    private const PAGE_3 = 'https://test-be.suqo.ai/api/v1/products/?page=3';

    public function testP1AllRecordsAreYieldedInOrderAcrossPages(): void
    {
        $client = (new MockHttpClient())
            ->pushJson(200, self::page(['a', 'b'], self::PAGE_2))
            ->pushJson(200, self::page(['c'], self::PAGE_3))
            ->pushJson(200, self::page(['d', 'e'], null));

        $ids = [];
        foreach ((new Products($this->transport($client)))->autoPaging() as $product) {
            self::assertInstanceOf(Product::class, $product);
            $ids[] = $product->productId;
        }

        self::assertSame(['a', 'b', 'c', 'd', 'e'], $ids);
        self::assertSame(3, $client->attempts());
    }

    public function testP2StoppingAfterTheFirstRecordFetchesOnlyPageOne(): void
    {
        $client = (new MockHttpClient())
            ->pushJson(200, self::page(['a', 'b'], self::PAGE_2))
            ->pushJson(200, self::page(['c'], null));

        foreach ((new Products($this->transport($client)))->autoPaging() as $product) {
            self::assertSame('a', $product->productId);
            break;
        }

        self::assertSame(1, $client->attempts(), 'Auto-paging must be lazy.');
    }

    public function testP3NullNextOnPageOneMakesExactlyOneRequest(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::page(['a'], null));

        $ids = [];
        foreach ((new Products($this->transport($client)))->autoPaging() as $product) {
            $ids[] = $product->productId;
        }

        self::assertSame(['a'], $ids);
        self::assertSame(1, $client->attempts());
    }

    public function testP4PageTwoIsRetriedAndIterationContinues(): void
    {
        $client = (new MockHttpClient())
            ->pushJson(200, self::page(['a'], self::PAGE_2))
            ->pushJson(500, ['detail' => 'boom'], [], 2)
            ->pushJson(200, self::page(['b'], null));

        $ids = [];
        foreach ((new Products($this->transport($client)))->autoPaging() as $product) {
            $ids[] = $product->productId;
        }

        self::assertSame(['a', 'b'], $ids);
        self::assertSame(4, $client->attempts());
    }

    public function testP5CancellationBetweenPagesStopsBeforeTheNextFetch(): void
    {
        $client = (new MockHttpClient())
            ->pushJson(200, self::page(['a'], self::PAGE_2))
            ->pushJson(200, self::page(['b'], null));

        $cancellation = new Cancellation();
        $seen = [];

        try {
            foreach ((new Products($this->transport($client)))->autoPaging(cancellation: $cancellation) as $product) {
                $seen[] = $product->productId;
                $cancellation->cancel();
            }
            self::fail('Expected CancelledError.');
        } catch (CancelledError) {
        }

        self::assertSame(['a'], $seen);
        self::assertSame(1, $client->attempts(), 'Page 2 must never be requested.');
    }

    public function testFirstPageUrlCarriesTheQueryParameters(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::page(['a'], null));

        foreach ((new Products($this->transport($client)))->autoPaging(pageSize: 25) as $_product) {
            break;
        }

        self::assertStringContainsString('page_size=25', $client->lastRequest()->url);
    }

    public function testPageShapeIsSurfaced(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::page(['a', 'b'], self::PAGE_2));

        $page = (new Products($this->transport($client)))->list();

        self::assertSame(2, $page->count);
        self::assertSame(self::PAGE_2, $page->next);
        self::assertNull($page->previous);
        self::assertCount(2, $page->results);
    }

    /**
     * @param  list<string>         $ids
     * @return array<string, mixed>
     */
    private static function page(array $ids, ?string $next): array
    {
        return [
            'count' => count($ids),
            'next' => $next,
            'previous' => null,
            'results' => array_map(
                static fn (string $id): array => [
                    'product_id' => $id,
                    'name' => 'Product ' . $id,
                    'type' => 'simple',
                    'is_active' => true,
                    'vat' => '13.00',
                    'total_subscribers' => '42',
                    'plan' => [
                        ['plan_id' => 'plan_' . $id, 'plan_name' => 'Basic'],
                    ],
                ],
                $ids,
            ),
        ];
    }
}
