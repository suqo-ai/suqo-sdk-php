<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Suqo\Model\Product;
use Suqo\Model\Subscription;
use Suqo\Resource\Products;
use Suqo\Resource\Subscriptions;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * §13 — Decimals, D1..D3.
 *
 * openapi puts `price` on the product object embedded in a subscription, not on
 * the product list record, so D1 is asserted where the field actually lives.
 */
final class DecimalsTest extends TransportTestCase
{
    public function testD1PriceIsSurfacedAsAString(): void
    {
        $product = $this->firstSubscription(['price' => '1000.00'])->product;

        self::assertNotNull($product);
        self::assertIsString($product->price);
        self::assertSame('1000.00', $product->price);
    }

    public function testD3TotalSubscribersIsSurfacedAsAString(): void
    {
        $product = $this->firstProduct(['total_subscribers' => '42']);

        self::assertSame('42', $product->totalSubscribers);
        self::assertIsString($product->totalSubscribers);
    }

    public function testVatIsSurfacedAsAString(): void
    {
        self::assertSame('13.00', $this->firstProduct(['vat' => '13.00'])->vat);
    }

    /**
     * D2 — no float or double appears anywhere in the type surface. Timeouts are
     * exempt: they are durations, not decimal wire values, and live outside these
     * namespaces.
     *
     * @return list<array{string}>
     */
    public static function modelClasses(): array
    {
        $classes = [];

        foreach (glob(__DIR__ . '/../../src/Model/*.php') ?: [] as $file) {
            $classes[] = ['Suqo\\Model\\' . basename($file, '.php')];
        }

        foreach (glob(__DIR__ . '/../../src/Params/*.php') ?: [] as $file) {
            $classes[] = ['Suqo\\Params\\' . basename($file, '.php')];
        }

        return $classes;
    }

    #[DataProvider('modelClasses')]
    public function testD2NoFloatInTheModelOrParamsTypeSurface(string $class): void
    {
        if (!class_exists($class) && !enum_exists($class)) {
            self::fail($class . ' must be loadable.');
        }

        $reflection = new ReflectionClass($class);

        /** @var array<string, string> $surface Type name => where it appeared. */
        $surface = [];

        foreach ($reflection->getProperties() as $property) {
            foreach (self::typeNames($property->getType()) as $name) {
                $surface[$name] = $class . '::$' . $property->getName();
            }
        }

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            $where = $class . '::' . $method->getName() . '()';

            foreach (self::typeNames($method->getReturnType()) as $name) {
                $surface[$name] = $where;
            }

            foreach ($method->getParameters() as $parameter) {
                foreach (self::typeNames($parameter->getType()) as $name) {
                    $surface[$name] = $where . ' $' . $parameter->getName();
                }
            }
        }

        self::assertArrayNotHasKey(
            'float',
            $surface,
            'A decimal value must never be typed as a float (§9.1).',
        );
    }

    /** @return list<string> */
    private static function typeNames(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName()];
        }

        if ($type instanceof ReflectionUnionType) {
            $names = [];
            foreach ($type->getTypes() as $inner) {
                $names = array_merge($names, self::typeNames($inner));
            }

            return $names;
        }

        return [];
    }

    /** @param array<string, mixed> $overrides */
    private function firstProduct(array $overrides): Product
    {
        $record = array_merge([
            'product_id' => 'prd_1',
            'name' => 'Pro Plan Bundle',
            'type' => 'simple',
            'is_active' => true,
            'vat' => '13.00',
            'total_subscribers' => '42',
        ], $overrides);

        $client = (new MockHttpClient())->pushJson(200, [
            'count' => 1,
            'next' => null,
            'previous' => null,
            'results' => [$record],
        ]);

        $page = (new Products($this->transport($client)))->list();
        self::assertCount(1, $page->results);

        return $page->results[0];
    }

    /** @param array<string, mixed> $productOverrides */
    private function firstSubscription(array $productOverrides): Subscription
    {
        $record = [
            'subscription_id' => 'sub_1',
            'status' => 'active',
            'is_active' => true,
            'product' => array_merge([
                'product_id' => 'prd_1',
                'name' => 'Pro Plan Bundle',
                'pbp_id' => 'pbp_3n9k2x',
                'price' => '999.00',
                'currency' => 'NPR',
            ], $productOverrides),
        ];

        $client = (new MockHttpClient())->pushJson(200, [
            'count' => 1,
            'next' => null,
            'previous' => null,
            'results' => [$record],
        ]);

        $page = (new Subscriptions($this->transport($client)))->list();
        self::assertCount(1, $page->results);

        return $page->results[0];
    }
}
