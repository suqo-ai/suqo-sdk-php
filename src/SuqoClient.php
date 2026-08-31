<?php

declare(strict_types=1);

namespace Suqo;

use Suqo\Http\HttpClientInterface;
use Suqo\Http\RetryPolicy;
use Suqo\Http\Transport;
use Suqo\Http\UrlBuilder;
use Suqo\Logging\Logger;
use Suqo\Resource\Customers;
use Suqo\Resource\Products;
use Suqo\Resource\Subscriptions;

/**
 * N9 — the client type, named for the vendor.
 *
 * §5 — top-level wiring; exposes resources and nothing else.
 *
 * ```php
 * $suqo = new SuqoClient();                       // key from SUQO_API_KEY
 * $page = $suqo->products->list(pageSize: 50);
 *
 * foreach ($suqo->subscriptions->autoPaging() as $subscription) {
 *     echo $subscription->customer?->email, PHP_EOL;
 * }
 * ```
 */
final class SuqoClient
{
    public readonly Config $config;

    public readonly Products $products;

    public readonly Subscriptions $subscriptions;

    public readonly Customers $customers;

    private readonly Transport $transport;

    /**
     * §4.1 — options. B2: named arguments, so adding an option never breaks an
     * existing call site.
     *
     * @param string|null                  $apiKey      Defaults to $SUQO_API_KEY.
     * @param Environment|string|null      $environment A check against the key
     *        prefix, never an override (§4.2).
     * @param float|null                   $timeout     Seconds; defaults to 30.
     * @param int|null                     $maxRetries  Defaults to 2.
     * @param LogLevel|string|null         $logLevel    Defaults to $SUQO_LOG, else warn.
     * @param HttpClientInterface|null     $httpClient  Defaults to cURL.
     *
     * @throws Exception\SuqoConfigError
     */
    public function __construct(
        ?string $apiKey = null,
        Environment|string|null $environment = null,
        ?float $timeout = null,
        ?int $maxRetries = null,
        LogLevel|string|null $logLevel = null,
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->config = Config::resolve(
            $apiKey,
            $environment,
            $timeout,
            $maxRetries,
            $logLevel,
            $httpClient,
        );

        $logger = new Logger($this->config->logLevel);

        $this->transport = new Transport(
            $this->config,
            new UrlBuilder($this->config->baseUrl),
            new RetryPolicy($this->config->maxRetries, $logger),
            $logger,
        );

        $this->products = new Products($this->transport);
        $this->subscriptions = new Subscriptions($this->transport);
        $this->customers = new Customers($this->transport);
    }

    /**
     * §12 — webhook verification does not need a client, and is exposed here only
     * as a convenience alias for discoverability.
     *
     * @see Webhook::verify()
     */
    public static function verifyWebhook(
        string $rawBody,
        ?string $signature,
        ?string $timestamp,
        string $secret,
        int $maxAge = Constants::WEBHOOK_MAX_AGE,
    ): bool {
        return Webhook::verify($rawBody, $signature, $timestamp, $secret, $maxAge);
    }
}
