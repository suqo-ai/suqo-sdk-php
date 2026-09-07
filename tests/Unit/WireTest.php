<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Suqo\Model\Wire;

/**
 * §9.1/§9.3 — the wire readers against the numeric JSON types the API is not
 * specified to send but could. The fixtures elsewhere always send decimals as
 * strings, so these paths have no other coverage.
 */
final class WireTest extends TestCase
{
    /** @return list<array{float, string|null}> */
    public static function decimalFloats(): array
    {
        return [
            [5.0, '5'],
            [5.5, '5.5'],
            [99.99, '99.99'],
            [0.1, '0.1'],
            [-13.0, '-13'],
            [0.0, '0'],
            [1.0e25, null],
            [1.0e-7, null],
            [NAN, null],
            [INF, null],
        ];
    }

    #[DataProvider('decimalFloats')]
    public function testDecimalStringifiesFloats(float $value, ?string $expected): void
    {
        self::assertSame($expected, Wire::decimal(['price' => $value], 'price'));
    }

    public function testDecimalPassesStringsThroughUntouched(): void
    {
        self::assertSame('1000.00', Wire::decimal(['price' => '1000.00'], 'price'));
    }

    public function testDecimalStringifiesInts(): void
    {
        self::assertSame('42', Wire::decimal(['price' => 42], 'price'));
    }

    /** @return list<array{mixed, string|null}> */
    public static function decimalNonNumerics(): array
    {
        return [[true, null], [null, null], [[], null], [['a' => 1], null]];
    }

    #[DataProvider('decimalNonNumerics')]
    public function testDecimalRejectsNonNumerics(mixed $value, ?string $expected): void
    {
        self::assertSame($expected, Wire::decimal(['price' => $value], 'price'));
    }

    public function testDecimalReadsAbsentKeyAsNull(): void
    {
        self::assertNull(Wire::decimal([], 'price'));
    }

    /** @return list<array{float, int|null}> */
    public static function countFloats(): array
    {
        return [
            [5.0, 5],
            [0.0, 0],
            [-3.0, -3],
            [1.0e6, 1000000],
            [5.5, null],
            [-0.5, null],
            [1.0e25, null],
            [NAN, null],
            [INF, null],
            [-INF, null],
        ];
    }

    #[DataProvider('countFloats')]
    public function testCountAcceptsIntegralFloatsOnly(float $value, ?int $expected): void
    {
        self::assertSame($expected, Wire::count(['count' => $value], 'count'));
    }

    public function testCountStillReadsIntsAndNumericStrings(): void
    {
        self::assertSame(7, Wire::count(['count' => 7], 'count'));
        self::assertSame(7, Wire::count(['count' => '7'], 'count'));
        self::assertSame(-7, Wire::count(['count' => '-7'], 'count'));
        self::assertNull(Wire::count(['count' => '7.5'], 'count'));
        self::assertNull(Wire::count(['count' => ''], 'count'));
        self::assertNull(Wire::count([], 'count'));
    }
}
