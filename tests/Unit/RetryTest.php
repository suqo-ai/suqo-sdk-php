<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use Suqo\Constants;
use Suqo\Endpoints;
use Suqo\Exception\CancelledError;
use Suqo\Exception\NetworkError;
use Suqo\Exception\NotFoundError;
use Suqo\Exception\RateLimitError;
use Suqo\Exception\ServerError;
use Suqo\Exception\ValidationError;
use Suqo\Http\HttpClientException;
use Suqo\Http\RetryPolicy;
use Suqo\Tests\Support\LatchedCancellation;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * §13 — Retry, R1..R12.
 */
final class RetryTest extends TransportTestCase
{
    public function testR1GetRetriesTwiceThenSucceeds(): void
    {
        $client = (new MockHttpClient())
            ->pushJson(500, ['detail' => 'boom'], [], 2)
            ->pushJson(200, ['count' => 0]);

        $response = $this->transport($client)->request('GET', Endpoints::PRODUCTS);

        self::assertSame(200, $response->status);
        self::assertSame(3, $client->attempts());
    }

    public function testR2ThreeFailuresRaiseServerError(): void
    {
        $client = (new MockHttpClient())->pushJson(503, ['detail' => 'boom'], [], 3);

        try {
            $this->transport($client)->request('GET', Endpoints::PRODUCTS);
            self::fail('Expected ServerError.');
        } catch (ServerError $e) {
            self::assertSame(503, $e->status);
        }

        self::assertSame(3, $client->attempts());
    }

    public function testR3PostIsNotRetried(): void
    {
        $client = (new MockHttpClient())->pushJson(500, ['detail' => 'boom']);

        $this->expectException(ServerError::class);

        try {
            $this->transport($client)->request('POST', Endpoints::SUBSCRIPTIONS, ['pbp_id' => 'p_1']);
        } finally {
            self::assertSame(1, $client->attempts());
        }
    }

    public function testR4GetReturning400IsNotRetried(): void
    {
        $client = (new MockHttpClient())->pushJson(400, ['detail' => 'bad']);

        try {
            $this->transport($client)->request('GET', Endpoints::PRODUCTS);
            self::fail('Expected ValidationError.');
        } catch (ValidationError) {
        }

        self::assertSame(1, $client->attempts());
    }

    public function testR5GetReturning404IsNotRetried(): void
    {
        $client = (new MockHttpClient())->pushJson(404, ['detail' => 'nope']);

        try {
            $this->transport($client)->request('GET', Endpoints::PRODUCTS);
            self::fail('Expected NotFoundError.');
        } catch (NotFoundError) {
        }

        self::assertSame(1, $client->attempts());
    }

    public function testR6GetReturning429IsRetriedAndFailsAsRateLimitError(): void
    {
        $client = (new MockHttpClient())->pushJson(429, ['detail' => 'slow down'], [], 3);

        try {
            $this->transport($client)->request('GET', Endpoints::PRODUCTS);
            self::fail('Expected RateLimitError.');
        } catch (RateLimitError $e) {
            self::assertSame(429, $e->status);
        }

        self::assertSame(3, $client->attempts());
    }

    public function testR7RetryAfterIsHonouredAndNotJittered(): void
    {
        $delays = [];
        $client = (new MockHttpClient())
            ->pushJson(429, ['detail' => 'slow down'], ['Retry-After' => '2'])
            ->pushJson(200, []);

        $this->transport($client, sleeper: self::recordingSleeper($delays))
            ->request('GET', Endpoints::PRODUCTS);

        self::assertSame([2000], $delays);
    }

    public function testR8RetryAfterIsCappedAt60Seconds(): void
    {
        $delays = [];
        $client = (new MockHttpClient())
            ->pushJson(429, ['detail' => 'slow down'], ['Retry-After' => '120'])
            ->pushJson(200, []);

        $this->transport($client, sleeper: self::recordingSleeper($delays))
            ->request('GET', Endpoints::PRODUCTS);

        self::assertSame([Constants::RETRY_AFTER_CAP_MS], $delays);
    }

    public function testRetryAfterIsSurfacedOnTheError(): void
    {
        $client = (new MockHttpClient())->pushJson(429, ['detail' => 'slow'], ['Retry-After' => '7'], 3);

        try {
            $this->transport($client)->request('GET', Endpoints::PRODUCTS);
            self::fail('Expected RateLimitError.');
        } catch (RateLimitError $e) {
            self::assertSame(7.0, $e->retryAfter);
        }
    }

    public function testNonNumericRetryAfterIsIgnored(): void
    {
        $client = (new MockHttpClient())
            ->pushJson(429, ['detail' => 'slow'], ['Retry-After' => 'Wed, 21 Oct 2015 07:28:00 GMT'], 3);

        try {
            $this->transport($client)->request('GET', Endpoints::PRODUCTS);
            self::fail('Expected RateLimitError.');
        } catch (RateLimitError $e) {
            self::assertNull($e->retryAfter);
        }
    }

    public function testR9AttemptZeroDelaysUseFullJitterUnder500ms(): void
    {
        $error = new ServerError('Server error (500).', 500);
        $seen = [];

        for ($i = 0; $i < 200; $i++) {
            $delay = RetryPolicy::computeDelayMs(0, $error);
            self::assertGreaterThanOrEqual(0, $delay);
            self::assertLessThan(Constants::BASE_DELAY_MS, $delay);
            $seen[$delay] = true;
        }

        self::assertGreaterThan(1, count($seen), 'Full jitter must vary across runs.');
    }

    public function testR10AttemptOneDelaysUseFullJitterUnder1000ms(): void
    {
        $error = new ServerError('Server error (500).', 500);
        $seen = [];

        for ($i = 0; $i < 200; $i++) {
            $delay = RetryPolicy::computeDelayMs(1, $error);
            self::assertGreaterThanOrEqual(0, $delay);
            self::assertLessThan(1000, $delay);
            $seen[$delay] = true;
        }

        self::assertGreaterThan(1, count($seen), 'Full jitter must vary across runs.');
    }

    public function testComputedDelayIsCappedAtMaxDelay(): void
    {
        $error = new ServerError('Server error (500).', 500);

        for ($i = 0; $i < 50; $i++) {
            self::assertLessThan(Constants::MAX_DELAY_MS + 1, RetryPolicy::computeDelayMs(12, $error));
        }
    }

    public function testR11CancellationDuringBackoffStopsTheLoop(): void
    {
        $client = (new MockHttpClient())->pushJson(500, ['detail' => 'boom'], [], 3);

        // Observations: 1 the loop's pre-attempt check, 2 the transport's, 3 the
        // backoff sleep — which is where the token must trip.
        $cancellation = new LatchedCancellation(2);

        try {
            $this->transport($client)->request('GET', Endpoints::PRODUCTS, null, [], $cancellation);
            self::fail('Expected CancelledError.');
        } catch (CancelledError) {
        }

        self::assertSame(1, $client->attempts(), 'No further attempt after cancellation.');
    }

    public function testR12NetworkFailureThenSuccessIsRetried(): void
    {
        $client = (new MockHttpClient())
            ->push(new HttpClientException('Could not resolve host'))
            ->pushJson(200, ['count' => 1]);

        $response = $this->transport($client)->request('GET', Endpoints::PRODUCTS);

        self::assertSame(200, $response->status);
        self::assertSame(2, $client->attempts());
    }

    public function testMaxRetriesZeroMakesASingleAttempt(): void
    {
        $client = (new MockHttpClient())->pushJson(500, ['detail' => 'boom']);

        try {
            $this->transport($client, maxRetries: 0)->request('GET', Endpoints::PRODUCTS);
            self::fail('Expected ServerError.');
        } catch (ServerError) {
        }

        self::assertSame(1, $client->attempts());
    }

    public function testWritesRetryableIsFalseAndEligibilityFollowsIt(): void
    {
        // WRITES_RETRYABLE is false, so a write is never eligible; flipping the
        // constant fails the first assertion below rather than a restatement of it.
        self::assertFalse(RetryPolicy::isEligible('POST', new ServerError('Server error (500).', 500)));
        self::assertTrue(RetryPolicy::isEligible('GET', new ServerError('Server error (500).', 500)));
        self::assertTrue(RetryPolicy::isEligible('GET', new NetworkError('boom', 0)));
        self::assertFalse(RetryPolicy::isEligible('GET', new CancelledError('cancelled', 0)));
        self::assertFalse(RetryPolicy::isEligible('GET', new NotFoundError('Not found.', 404)));
    }
}
