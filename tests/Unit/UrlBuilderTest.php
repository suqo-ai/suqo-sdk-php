<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Suqo\Endpoints;
use Suqo\Http\UrlBuilder;

/**
 * §13 — URL building, U1..U5.
 */
final class UrlBuilderTest extends TestCase
{
    public function testU1TrailingSlashOnBaseUrlIsNotDoubled(): void
    {
        $url = (new UrlBuilder('https://be.suqo.ai/'))->build(Endpoints::PRODUCTS);

        self::assertSame('https://be.suqo.ai/api/v1/products/', $url);
        self::assertStringNotContainsString('//api', $url);
    }

    public function testU2TrailingSlashIsAppendedToPath(): void
    {
        self::assertSame(
            'https://be.suqo.ai/api/v1/products/',
            (new UrlBuilder('https://be.suqo.ai'))->build('/api/v1/products'),
        );
    }

    public function testU2AlreadySlashedPathIsUnchanged(): void
    {
        self::assertSame(
            'https://be.suqo.ai/api/v1/products/',
            (new UrlBuilder('https://be.suqo.ai'))->build('/api/v1/products/'),
        );
    }

    public function testU3AbsentQueryValuesAreOmitted(): void
    {
        $url = (new UrlBuilder('https://be.suqo.ai'))->build(Endpoints::PRODUCTS, [
            'page' => 2,
            'page_size' => null,
        ]);

        self::assertSame('https://be.suqo.ai/api/v1/products/?page=2', $url);
        self::assertStringNotContainsString('page_size', $url);
    }

    public function testU4ReservedCharactersArePercentEncoded(): void
    {
        $url = (new UrlBuilder('https://be.suqo.ai'))->build(Endpoints::PRODUCTS, [
            'q' => 'a&b=c',
        ]);

        self::assertSame('https://be.suqo.ai/api/v1/products/?q=a%26b%3Dc', $url);
    }

    public function testU5EmptyQueryMapProducesNoQuestionMark(): void
    {
        $url = (new UrlBuilder('https://be.suqo.ai'))->build(Endpoints::PRODUCTS, []);

        self::assertStringNotContainsString('?', $url);
    }

    public function testAllAbsentQueryValuesProduceNoQuestionMark(): void
    {
        $url = (new UrlBuilder('https://be.suqo.ai'))
            ->build(Endpoints::PRODUCTS, ['page' => null, 'page_size' => null]);

        self::assertStringNotContainsString('?', $url);
    }
}
