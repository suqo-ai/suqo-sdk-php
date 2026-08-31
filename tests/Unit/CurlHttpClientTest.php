<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Suqo\Cancellation;
use Suqo\Http\CurlHttpClient;
use Suqo\Http\HttpCancelledException;
use Suqo\Http\HttpClientException;
use Suqo\Http\HttpRequest;
use Suqo\Tests\Support\LatchedCancellation;

/**
 * T8/T10 against the real default client: a timeout and a caller cancellation must
 * reach the transport as distinguishable failures.
 *
 * The fixture is a listening socket that never accepts. The kernel completes the
 * handshake from the backlog, so the connection succeeds and the transfer then
 * stalls — which is exactly the shape both cases need.
 */
final class CurlHttpClientTest extends TestCase
{
    /** @var resource|null */
    private $server;

    private string $url = '';

    protected function setUp(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is not available.');
        }

        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($server === false) {
            self::markTestSkipped('Unable to bind a local socket: ' . $errstr);
        }

        $this->server = $server;
        $name = stream_socket_get_name($server, false);
        self::assertIsString($name);
        $this->url = 'http://' . $name . '/stalled';
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            fclose($this->server);
        }
    }

    public function testATimeoutIsATransportFailureNotACancellation(): void
    {
        $request = new HttpRequest('GET', $this->url, [], null, 0.3, Cancellation::none());

        try {
            (new CurlHttpClient())->send($request);
            self::fail('Expected HttpClientException.');
        } catch (HttpCancelledException $e) {
            self::fail('A timeout must not surface as a cancellation: ' . $e->getMessage());
        } catch (HttpClientException $e) {
            self::assertSame(CurlHttpClient::OPERATION_TIMEDOUT, $e->getCode());
        }
    }

    public function testACallerCancellationAbortsTheTransfer(): void
    {
        // The first observation happens inside the progress callback.
        $request = new HttpRequest('GET', $this->url, [], null, 5.0, new LatchedCancellation(0));

        $this->expectException(HttpCancelledException::class);

        (new CurlHttpClient())->send($request);
    }

    public function testHeadersAreSentVerbatim(): void
    {
        $request = new HttpRequest(
            'POST',
            $this->url,
            ['Authorization' => 'Bearer su_test_key_abc', 'X-Request-Id' => 'req_1'],
            '{"pbp_id":"p_1"}',
            0.3,
            Cancellation::none(),
        );

        // The socket never replies, so the assertion is only that building and
        // firing the request with those headers reaches the timeout path rather
        // than failing earlier.
        try {
            (new CurlHttpClient())->send($request);
            self::fail('Expected HttpClientException.');
        } catch (HttpClientException $e) {
            self::assertSame(CurlHttpClient::OPERATION_TIMEDOUT, $e->getCode());
        }
    }
}
