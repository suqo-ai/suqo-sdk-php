<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Suqo\Cancellation;
use Suqo\Endpoints;
use Suqo\Exception\CancelledError;
use Suqo\Exception\NetworkError;
use Suqo\Exception\SuqoError;
use Suqo\Http\HttpCancelledException;
use Suqo\Http\HttpClientException;
use Suqo\Http\HttpResponse;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * §13 — Transport, T1..T10.
 */
final class TransportTest extends TransportTestCase
{
    public function testT1AuthorizationHeaderIsPresent(): void
    {
        $client = (new MockHttpClient())->pushJson(200, []);
        $this->transport($client)->request('GET', Endpoints::PRODUCTS);

        self::assertSame('Bearer ' . self::KEY, $client->lastRequest()->headers['Authorization']);
    }

    public function testT2ContentTypeIsPresentWhenABodyIsSent(): void
    {
        $client = (new MockHttpClient())->pushJson(200, []);
        $this->transport($client)->request('POST', Endpoints::SUBSCRIPTIONS, ['pbp_id' => 'p_1']);

        self::assertSame('application/json', $client->lastRequest()->headers['Content-Type']);
        self::assertSame('{"pbp_id":"p_1"}', $client->lastRequest()->body);
    }

    public function testT3ContentTypeIsAbsentWithoutABody(): void
    {
        $client = (new MockHttpClient())->pushJson(200, []);
        $this->transport($client)->request('GET', Endpoints::PRODUCTS);

        self::assertArrayNotHasKey('Content-Type', $client->lastRequest()->headers);
        self::assertNull($client->lastRequest()->body);
    }

    public function testT4SentRequestIdEqualsTheReportedOne(): void
    {
        $client = (new MockHttpClient())->pushJson(200, []);
        $response = $this->transport($client)->request('GET', Endpoints::PRODUCTS);

        self::assertNotSame('', $response->requestId);
        self::assertSame($response->requestId, $client->lastRequest()->headers['X-Request-Id']);
    }

    public function testT5SentRequestIdEqualsTheOneOnTheRaisedError(): void
    {
        $client = (new MockHttpClient())->pushJson(404, ['detail' => 'nope']);

        try {
            $this->transport($client)->request('GET', Endpoints::PRODUCTS);
            self::fail('Expected SuqoError.');
        } catch (SuqoError $e) {
            self::assertNotSame('', $e->requestId);
            self::assertSame($e->requestId, $client->lastRequest()->headers['X-Request-Id']);
        }
    }

    public function testT6SequentialRequestsGetDifferentIds(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [], [], 2);
        $transport = $this->transport($client);

        $first = $transport->request('GET', Endpoints::PRODUCTS)->requestId;
        $second = $transport->request('GET', Endpoints::PRODUCTS)->requestId;

        self::assertNotSame($first, $second);
        self::assertNotSame(
            $client->requests[0]->headers['X-Request-Id'],
            $client->requests[1]->headers['X-Request-Id'],
        );
    }

    public function testEachRetryAttemptCarriesAFreshId(): void
    {
        $client = (new MockHttpClient())
            ->pushJson(500, ['detail' => 'boom'], [], 2)
            ->pushJson(200, []);

        $response = $this->transport($client)->request('GET', Endpoints::PRODUCTS);

        $ids = array_map(
            static fn ($request): string => $request->headers['X-Request-Id'],
            $client->requests,
        );

        self::assertCount(3, $ids);
        self::assertCount(3, array_unique($ids));
        self::assertSame($ids[2], $response->requestId);
    }

    public function testT7NonJsonBodyBecomesNull(): void
    {
        $client = (new MockHttpClient())->push(new HttpResponse(200, [], '<html>not json</html>'));

        $response = $this->transport($client)->request('GET', Endpoints::PRODUCTS);

        self::assertNull($response->body);
    }

    public function testEmptyBodyBecomesNull(): void
    {
        $client = (new MockHttpClient())->push(new HttpResponse(204, [], ''));

        self::assertNull($this->transport($client)->request('GET', Endpoints::PRODUCTS)->body);
    }

    public function testT8TransportFailureBecomesNetworkErrorWithStatusZero(): void
    {
        $client = (new MockHttpClient())->push(
            new HttpClientException('Operation timed out after 30000 milliseconds'),
            3,
        );

        try {
            $this->transport($client)->request('GET', Endpoints::PRODUCTS);
            self::fail('Expected NetworkError.');
        } catch (NetworkError $e) {
            self::assertSame(0, $e->status);
            self::assertStringContainsString('timed out', $e->getMessage());
        }
    }

    public function testT9CancellationBeforeTheCallMakesNoRequest(): void
    {
        $client = new MockHttpClient();
        $cancellation = new Cancellation();
        $cancellation->cancel();

        try {
            $this->transport($client)->request('GET', Endpoints::PRODUCTS, null, [], $cancellation);
            self::fail('Expected CancelledError.');
        } catch (CancelledError $e) {
            self::assertSame(0, $e->status);
        }

        self::assertSame(0, $client->attempts());
    }

    public function testT10MidFlightCancellationIsNotANetworkError(): void
    {
        $client = (new MockHttpClient())->push(new HttpCancelledException('aborted by callback'));

        $this->expectException(CancelledError::class);

        $this->transport($client)->request('GET', Endpoints::PRODUCTS, null, [], new Cancellation());
    }

    public function testRequestIdIsPresentOnACancelledMidFlightRequest(): void
    {
        $client = (new MockHttpClient())->push(new HttpCancelledException('aborted by callback'));

        try {
            $this->transport($client)->request('GET', Endpoints::PRODUCTS);
            self::fail('Expected CancelledError.');
        } catch (CancelledError $e) {
            self::assertSame($e->requestId, $client->lastRequest()->headers['X-Request-Id']);
        }
    }

    public function testEmptyBodyObjectSerialisesAsAJsonObject(): void
    {
        $client = (new MockHttpClient())->pushJson(200, []);
        $this->transport($client)->request('POST', Endpoints::subscriptionCancel('sub_1'), []);

        self::assertSame('{}', $client->lastRequest()->body);
    }

    public function testTimeoutIsForwardedToTheHttpClient(): void
    {
        $client = (new MockHttpClient())->pushJson(200, []);
        $this->transport($client, timeout: 1.5)->request('GET', Endpoints::PRODUCTS);

        self::assertSame(1.5, $client->lastRequest()->timeout);
    }

    /**
     * §6.6 — an absolute URL may only address the configured origin.
     *
     * Every request carries the API key, and getAbsolute() is the only entry
     * point that accepts a URL the SDK did not build, so a `next` link pointing
     * off host would hand the key to that host.
     */
    public function testAbsoluteUrlOnTheConfiguredOriginIsFollowed(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['count' => 0, 'results' => []]);

        $this->transport($client)->getAbsolute('https://test-be.suqo.ai/api/v1/products/?page=2');

        self::assertSame(1, $client->attempts());
    }

    public function testAbsoluteUrlHostIsComparedCaseInsensitively(): void
    {
        $client = (new MockHttpClient())->pushJson(200, []);

        $this->transport($client)->getAbsolute('https://TEST-BE.SUQO.AI/api/v1/products/');

        self::assertSame(1, $client->attempts());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function offOriginUrls(): array
    {
        return [
            'different host' => ['https://api.suqo.ai/api/v1/products/?page=2'],
            'live host from a sandbox client' => ['https://be.suqo.ai/api/v1/products/?page=2'],
            'attacker host' => ['https://evil.example.com/api/v1/products/?page=2'],
            'subdomain prefix' => ['https://test-be.suqo.ai.evil.example.com/api/v1/products/'],
            'downgraded scheme' => ['http://test-be.suqo.ai/api/v1/products/'],
            'explicit port' => ['https://test-be.suqo.ai:8443/api/v1/products/'],
            'scheme-relative' => ['//evil.example.com/api/v1/products/'],
            'not a url' => ['nonsense'],
            'empty' => [''],
        ];
    }

    #[DataProvider('offOriginUrls')]
    public function testAbsoluteUrlOffTheConfiguredOriginIsRefused(string $url): void
    {
        $client = (new MockHttpClient())->pushJson(200, []);

        try {
            $this->transport($client)->getAbsolute($url);
            self::fail('Expected the off-origin URL to be refused.');
        } catch (NetworkError $e) {
            self::assertStringContainsString('does not match the configured', $e->getMessage());
            self::assertSame(0, $e->status);
        }

        // Refused before the request is made, not after: nothing was sent, so
        // the API key never reached the other host.
        self::assertSame(0, $client->attempts());
    }

    /**
     * The check precedes the retry loop — a refused URL cannot succeed on a
     * second attempt, so it must not consume retries or sleep.
     */
    public function testARefusedUrlIsNotRetried(): void
    {
        $client = (new MockHttpClient())->pushJson(200, []);

        try {
            $this->transport($client, maxRetries: 2)->getAbsolute('https://evil.example.com/x/');
        } catch (NetworkError) {
            // expected
        }

        self::assertSame(0, $client->attempts());
    }
}
