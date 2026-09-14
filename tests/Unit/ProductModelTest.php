<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Suqo\Model\Product;

/**
 * §9 — the Product record, decoded from a real `GET /api/v1/products/` payload
 * captured 2026-09-11.
 *
 * `vat`, `product_image` and `plan[].billing_periods` are all structured, not
 * scalars. An earlier revision typed the first two as `?string` and the third as
 * `?string` on ProductPlan, which decoded every one of them to null — and since
 * `pbp_id` lives on a billing period and is the required input to
 * `subscriptions.create()`, that left the primary integration flow with no typed
 * path at all. These tests pin the shape so it cannot regress.
 */
final class ProductModelTest extends TestCase
{
    public function testPbpIdIsReachableThroughTheTypedSurface(): void
    {
        $product = Product::fromWire(self::liveRecord());

        $plan = $product->plan[0] ?? null;
        self::assertNotNull($plan);
        self::assertSame('4', $plan->planId);

        $period = $plan->billingPeriods[0] ?? null;
        self::assertNotNull($period);
        self::assertSame('pbp_350d94851', $period->pbpId);
        self::assertSame('day', $period->intervalType);
        self::assertSame(1, $period->intervalCount);
        self::assertSame('Daily', $period->label);
        self::assertSame('100.00', $period->price);
        self::assertSame('NPR', $period->currency);
        self::assertTrue($period->isCurrent);
        self::assertTrue($period->isLimited);
        self::assertFalse($period->isArchived);
        self::assertSame([], $period->offers);
    }

    /** `plan_id` is a string holding an integer — not a UUID, and not to be parsed. */
    public function testPlanIdStaysAString(): void
    {
        $plan = Product::fromWire(self::liveRecord())->plan[0] ?? null;

        self::assertNotNull($plan);
        self::assertIsString($plan->planId);
    }

    /** A null description on the plan and an empty one on the product, in one payload. */
    public function testDescriptionsAreNullable(): void
    {
        $product = Product::fromWire(self::liveRecord());

        self::assertSame('', $product->description);
        self::assertNull($product->plan[0]->description);
    }

    public function testVatIsAnObjectWithNullableMembers(): void
    {
        $vat = Product::fromWire(self::liveRecord())->vat;

        self::assertNotNull($vat);
        self::assertFalse($vat->isVatActive);
        self::assertNull($vat->vatType);
        self::assertNull($vat->vatPercentage);
    }

    public function testProductImageIsAList(): void
    {
        self::assertSame([], Product::fromWire(self::liveRecord())->productImage);

        $withImages = Product::fromWire(self::liveRecord([
            'product_image' => [
                ['image' => 'https://cdn.suqo.ai/a.png', 'image_order' => 0],
                ['image' => 'https://cdn.suqo.ai/b.png', 'image_order' => 1],
            ],
        ]));

        self::assertCount(2, $withImages->productImage);
        self::assertSame('https://cdn.suqo.ai/a.png', $withImages->productImage[0]->image);
        self::assertSame(1, $withImages->productImage[1]->imageOrder);
    }

    /**
     * Timestamps carry microseconds and a +05:45 offset rather than the `Z` the
     * spec examples show. They stay opaque strings (§9.1), so the SDK is
     * indifferent — this pins that it never normalises them.
     */
    public function testTimestampsArePassedThroughVerbatim(): void
    {
        $product = Product::fromWire(self::liveRecord());

        self::assertSame('2026-08-19T09:44:14.455808+05:45', $product->createdAt);
        self::assertSame('2026-08-19T09:44:14.455821+05:45', $product->updatedAt);
    }

    public function testAMissingVatDecodesToNullRatherThanThrowing(): void
    {
        $record = self::liveRecord();
        unset($record['vat']);

        self::assertNull(Product::fromWire($record)->vat);
    }

    public function testAMissingPlanDecodesToAnEmptyList(): void
    {
        $record = self::liveRecord();
        unset($record['plan']);

        self::assertSame([], Product::fromWire($record)->plan);
    }

    /**
     * The live `GET /api/v1/products/` `results[0]`, verbatim.
     *
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function liveRecord(array $overrides = []): array
    {
        return array_merge([
            'product_id' => '036b88f1-2055-44a7-89c3-e27b53a253a2',
            'name' => 'Test',
            'description' => '',
            'type' => 'simple',
            'is_active' => true,
            'terms_and_conditions' => '',
            'features_and_benefits' => '',
            'vat' => [
                'is_vat_active' => false,
                'vat_type' => null,
                'vat_percentage' => null,
            ],
            'product_image' => [],
            'plan' => [
                [
                    'plan_id' => '4',
                    'plan_name' => '',
                    'description' => null,
                    'billing_periods' => [
                        [
                            'pbp_id' => 'pbp_350d94851',
                            'interval_type' => 'day',
                            'interval_count' => 1,
                            'label' => 'Daily',
                            'price' => '100.00',
                            'currency' => 'NPR',
                            'is_current' => true,
                            'is_limited' => true,
                            'is_archived' => false,
                            'offers' => [],
                        ],
                    ],
                ],
            ],
            'total_subscribers' => '0',
            'created_at' => '2026-08-19T09:44:14.455808+05:45',
            'updated_at' => '2026-08-19T09:44:14.455821+05:45',
        ], $overrides);
    }
}
