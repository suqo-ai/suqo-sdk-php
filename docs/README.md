# SUQO PHP SDK — API reference

Per-method reference for `suqo/sdk-php`. The [README](../README.md) is the quick
start and explains the cross-cutting rules; this folder documents every public
method, one entry each.

Everything is namespaced under `Suqo\`, PSR-4 from `src/`.

## Pages

| Page | Covers |
| --- | --- |
| [client.md](client.md) | `SuqoClient`, `Config`, `Environment`, `LogLevel` |
| [products.md](products.md) | `Resource\Products` |
| [subscriptions.md](subscriptions.md) | `Resource\Subscriptions` |
| [customers.md](customers.md) | `Resource\Customers` — `list`, `autoPaging`, `read` (read-only) |
| [webhooks.md](webhooks.md) | `Webhook::verify`, `SuqoClient::verifyWebhook` |
| [errors.md](errors.md) | the exception hierarchy, error fields, `ErrorMapper` |
| [models.md](models.md) | response models, params objects, `SubscriptionStatus` |
| [http.md](http.md) | the HTTP layer, retries, pagination, cancellation, logging |

## Method map

Everything callable, in one table.

| Call | Returns | Page |
| --- | --- | --- |
| `new SuqoClient(…)` | `SuqoClient` | [client](client.md#__construct) |
| `SuqoClient::verifyWebhook(…)` | `bool` | [webhooks](webhooks.md#suqoclientverifywebhook) |
| `$suqo->products->list(…)` | `Page<Product>` | [products](products.md#list) |
| `$suqo->products->autoPaging(…)` | `Generator<int, Product>` | [products](products.md#autopaging) |
| `$suqo->subscriptions->list(…)` | `SubscriptionPage` | [subscriptions](subscriptions.md#list) |
| `$suqo->subscriptions->autoPaging(…)` | `Generator<int, Subscription>` | [subscriptions](subscriptions.md#autopaging) |
| `$suqo->subscriptions->create(…)` | `CreateSubscriptionResponse` | [subscriptions](subscriptions.md#create) |
| `$suqo->subscriptions->cancel(…)` | `MessageResponse` | [subscriptions](subscriptions.md#cancel) |
| `$suqo->subscriptions->updateBillingCycle(…)` | `MessageResponse` | [subscriptions](subscriptions.md#updatebillingcycle) |
| `$suqo->customers->list(…)` | `Page<Customer>` | [customers](customers.md#list) |
| `$suqo->customers->autoPaging(…)` | `Generator<int, Customer>` | [customers](customers.md#autopaging) |
| `$suqo->customers->read(…)` | `Customer` | [customers](customers.md#read) |
| `Webhook::verify(…)` | `bool` | [webhooks](webhooks.md#verify) |
| `Config::resolve(…)` | `Config` | [client](client.md#configresolve) |
| `Environment::baseUrl()` | `string` | [client](client.md#environmentbaseurl) |
| `LogLevel::severity()` / `::emits()` | `int` / `bool` | [client](client.md#loglevelseverity) |
| `Model::toArray()` | `array` | [models](models.md#modeltoarray) |
| `Page::fromWire(…)` | `Page<T>` | [models](models.md#pagefromwire) |
| `SubscriptionPage::fromSubscriptionsWire(…)` | `SubscriptionPage` | [models](models.md#subscriptionpagefromsubscriptionswire) |
| `<Record>::fromWire(…)` | that record | [models](models.md#record-models) |
| `SubscriptionStatus::parse(…)` | `SubscriptionStatus\|string\|null` | [models](models.md#subscriptionstatusparse) |
| `<Params>::toWire()` / `::fromWire(…)` | `array` / that params object | [models](models.md#params-objects) |
| `Cancellation::none()` / `->cancel()` / `->isCancelled()` | `Cancellation` / `void` / `bool` | [http](http.md#cancellation) |
| `Pagination::autoPage(…)` | `Generator<int, T>` | [http](http.md#paginationautopage) |
| `Transport::request()` / `->url()` / `->getAbsolute()` | `TransportResponse` / `string` / `TransportResponse` | [http](http.md#transport) |
| `RetryPolicy::execute()` / `::isEligible()` / `::computeDelayMs()` | `mixed` / `bool` / `int` | [http](http.md#retrypolicy) |
| `UrlBuilder::build(…)` | `string` | [http](http.md#urlbuilderbuild) |
| `HttpClientInterface::send(…)` | `HttpResponse` | [http](http.md#httpclientinterfacesend) |
| `HttpResponse::header(…)` | `?string` | [http](http.md#httpresponse) |
| `TransportResponse::object()` | `array` | [http](http.md#transportresponse) |
| `Logger::debug/info/warn/error()` / `->level()` | `void` / `LogLevel` | [http](http.md#logger) |
| `Endpoints::subscriptionCancel(…)` | `string` | [http](http.md#endpoints) |
| `Constants::writesRetryable()` / `::msgEnvConflict()` | `bool` / `string` | [http](http.md#constants) |
| `ErrorMapper::map(…)` | `SuqoError` | [errors](errors.md#errormappermap) |

## Conventions across the whole surface

- **Named arguments.** Every optional parameter is passed by name, so a future
  version can add one without breaking a call site.
- **Money and decimals are strings.** `price`, `vat`, `totalSubscribers` and every
  date stay `string` end to end and are never parsed into a float inside the SDK.
- **Wire names survive.** `toArray()` on any model returns the decoded payload with
  the server's own keys, so a field the SDK does not yet type is still reachable.
- **`cancellation` is always last and always optional.** See
  [http.md#cancellation](http.md#cancellation).
- **Errors are a hierarchy.** Every failure raised after construction derives from
  `Suqo\Exception\SuqoError`; construction itself raises `SuqoConfigError`.
