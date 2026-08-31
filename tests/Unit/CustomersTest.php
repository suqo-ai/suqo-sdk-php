<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Suqo\Constants;
use Suqo\Exception\NotImplementedError;
use Suqo\Exception\SuqoError;
use Suqo\Model\Customer;
use Suqo\Resource\Customers;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * §13 — Customers, N1.
 */
final class CustomersTest extends TransportTestCase
{
    /** @return list<array{string, list<mixed>}> */
    public static function operations(): array
    {
        return [
            ['list', []],
            ['autoPaging', []],
            ['read', ['cus_1']],
        ];
    }

    /**
     * @param list<mixed> $args
     */
    #[DataProvider('operations')]
    public function testN1EveryOperationRaisesNotImplemented(string $operation, array $args): void
    {
        $client = new MockHttpClient();
        $customers = new Customers($this->transport($client));

        try {
            $result = $customers->{$operation}(...$args);

            // A generator-typed operation must still refuse on the spot.
            if ($result instanceof \Generator) {
                iterator_to_array($result);
            }

            self::fail('Expected NotImplementedError from ' . $operation . '().');
        } catch (NotImplementedError $e) {
            self::assertSame(Constants::MSG_NOT_IMPLEMENTED, $e->getMessage());
            self::assertSame(0, $e->status);
            self::assertInstanceOf(SuqoError::class, $e);
        }

        self::assertSame(0, $client->attempts(), 'No request may be made.');
    }

    public function testTheCustomersResourceIsWiredOntoTheClient(): void
    {
        $suqo = new \Suqo\SuqoClient(apiKey: self::KEY, httpClient: new MockHttpClient());

        self::assertInstanceOf(Customers::class, $suqo->customers);
    }

    /**
     * §9.4 — the Customer full-record type is un-stubbed in the same change that
     * adds the Customers endpoints to openapi.yaml. Those endpoints now exist, so
     * the record type mirrors `ExternalCustomer` even though §10.3 keeps the
     * operations unimplemented.
     */
    public function testTheCustomerRecordTypeMirrorsTheSchema(): void
    {
        $customer = Customer::fromWire([
            'id' => 7,
            'buyer_phone' => '9841000100',
            'buyer_email' => 'ram@client.com',
            'full_name' => 'Ram Shrestha',
            'created_at' => '2026-07-03T10:15:00Z',
        ]);

        self::assertSame(7, $customer->id);
        self::assertSame('9841000100', $customer->buyerPhone);
        self::assertSame('ram@client.com', $customer->buyerEmail);
        self::assertSame('Ram Shrestha', $customer->fullName);
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
