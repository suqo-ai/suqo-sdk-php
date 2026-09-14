<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use ReflectionClass;
use Suqo\Model\Customer;
use Suqo\Resource\Customers;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * §13 — Customers.
 */
final class CustomersTest extends TransportTestCase
{
    /**
     * The list body is the ordinary `{count, next, previous, results}` envelope,
     * not the bare array openapi's response schema declares — confirmed against
     * a live `GET /api/v1/customers/` 2026-09-11. The declared schema is a
     * generation artifact; this test is what pins the real shape.
     */
    public function testListDecodesTheStandardEnvelope(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::liveEnvelope());

        $page = (new Customers($this->transport($client)))->list();

        self::assertSame(7, $page->count);
        self::assertNull($page->next);
        self::assertNull($page->previous);
        self::assertCount(2, $page->results);
        self::assertSame('cus_0390b1820', $page->results[0]->id);
        self::assertSame('GET', $client->lastRequest()->method);
        self::assertStringContainsString('/api/v1/customers/', $client->lastRequest()->url);
    }

    /** Every field but `id`, `buyer_phone` and `created_at` can be null in practice. */
    public function testNullableFieldsDecodeToNull(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::liveEnvelope());

        $sparse = (new Customers($this->transport($client)))->list()->results[1];

        self::assertSame('cus_58a3ae20d', $sparse->id);
        self::assertSame('9876543165', $sparse->buyerPhone);
        self::assertNull($sparse->buyerEmail);
        self::assertNull($sparse->fullName);
        self::assertNull($sparse->address);
        self::assertSame('2026-08-07T13:21:07.329099+05:45', $sparse->createdAt);
    }

    public function testListForwardsPageParameters(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::liveEnvelope());

        (new Customers($this->transport($client)))->list(page: 2, pageSize: 50);

        self::assertStringContainsString('page=2', $client->lastRequest()->url);
        self::assertStringContainsString('page_size=50', $client->lastRequest()->url);
    }

    public function testAutoPagingYieldsEveryRecord(): void
    {
        $client = (new MockHttpClient())
            ->pushJson(200, [
                'count' => 2,
                'next' => 'https://test-be.suqo.ai/api/v1/customers/?page=2',
                'previous' => null,
                'results' => [self::liveRecords()[0]],
            ])
            ->pushJson(200, [
                'count' => 2,
                'next' => null,
                'previous' => null,
                'results' => [self::liveRecords()[1]],
            ]);

        $ids = [];

        foreach ((new Customers($this->transport($client)))->autoPaging() as $customer) {
            $ids[] = $customer->id;
        }

        self::assertSame(['cus_0390b1820', 'cus_58a3ae20d'], $ids);
        self::assertSame(2, $client->attempts());
    }

    public function testReadFetchesOneCustomerByPublicId(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::liveRecords()[0]);

        $customer = (new Customers($this->transport($client)))->read('cus_0390b1820');

        self::assertSame('cus_0390b1820', $customer->id);
        self::assertStringContainsString('/api/v1/customers/cus_0390b1820/', $client->lastRequest()->url);
    }

    /** The id travels in the path, so it is escaped rather than trusted. */
    public function testReadEscapesThePathSegment(): void
    {
        $client = (new MockHttpClient())->pushJson(200, self::liveRecords()[0]);

        (new Customers($this->transport($client)))->read('cus_1/../subscriptions');

        self::assertStringNotContainsString('/../', $client->lastRequest()->url);
    }

    /**
     * Two records from the live `GET /api/v1/customers/` body: one fully
     * populated, one with every optional field null.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private static function liveRecords(): array
    {
        return [
            [
                'id' => 'cus_0390b1820',
                'buyer_phone' => '9782342342',
                'buyer_email' => 'rebika@codepros.ai',
                'full_name' => 'asdeasd',
                'address' => 'Shankhamul, Kathmandu 44600, Nepal',
                'created_at' => '2026-08-17T13:03:32.640906+05:45',
            ],
            [
                'id' => 'cus_58a3ae20d',
                'buyer_phone' => '9876543165',
                'buyer_email' => null,
                'full_name' => null,
                'address' => null,
                'created_at' => '2026-08-07T13:21:07.329099+05:45',
            ],
        ];
    }

    /**
     * The live envelope, with `count` left at its real value of 7 even though
     * only two records are carried — exactly what a first page looks like.
     *
     * @return array<string, mixed>
     */
    private static function liveEnvelope(): array
    {
        return [
            'count' => 7,
            'next' => null,
            'previous' => null,
            'results' => self::liveRecords(),
        ];
    }

    public function testTheCustomersResourceIsWiredOntoTheClient(): void
    {
        $suqo = new \Suqo\SuqoClient(apiKey: self::KEY, httpClient: new MockHttpClient());

        self::assertInstanceOf(Customers::class, $suqo->customers);
    }

    /**
     * §9.4 — the Customer full-record type mirrors `ExternalCustomer`.
     */
    public function testTheCustomerRecordTypeMirrorsTheSchema(): void
    {
        $customer = Customer::fromWire([
            'id' => 'cus_0390b1820',
            'buyer_phone' => '9841000100',
            'buyer_email' => 'ram@client.com',
            'full_name' => 'Ram Shrestha',
            'address' => 'Shankhamul, Kathmandu 44600, Nepal',
            'created_at' => '2026-07-03T10:15:00Z',
        ]);

        // A prefixed public id, not an integer and not a UUID — confirmed
        // against a live response 2026-09-11.
        self::assertSame('cus_0390b1820', $customer->id);
        self::assertSame('9841000100', $customer->buyerPhone);
        self::assertSame('ram@client.com', $customer->buyerEmail);
        self::assertSame('Ram Shrestha', $customer->fullName);
        self::assertSame('Shankhamul, Kathmandu 44600, Nepal', $customer->address);
        self::assertSame('2026-07-03T10:15:00Z', $customer->createdAt);
    }

    /**
     * N4 — operation names come from the operationIds openapi declares:
     * `customers_list` and `customers_read`, minus the resource noun.
     */
    public function testTheOperationSetMatchesTheDeclaredOperationIds(): void
    {
        $declared = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            array_filter(
                (new ReflectionClass(Customers::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
                static fn (\ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === Customers::class,
            ),
        );

        sort($declared);

        self::assertSame(['autoPaging', 'list', 'read'], $declared);
    }
}
