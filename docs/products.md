# Products

`$suqo->products` — `Suqo\Resource\Products`. Two methods: one page, or every page
lazily.

Price is not on the product record. It lives on the plan billing point and surfaces
as `$subscription->product->price` once a subscription exists.

## `list`

```php
public function list(
    ?int $page = null,
    ?int $pageSize = null,
    ?Cancellation $cancellation = null,
): Page
```

`GET /api/v1/products/` — one page of products.

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `page` | `?int` | `null` | 1-based. Omitted from the query string entirely when null. |
| `pageSize` | `?int` | `null` | Sent as `page_size`. Omitted when null. |
| `cancellation` | `?Cancellation` | `null` | See [http.md#cancellation](http.md#cancellation). |

**Returns** `Suqo\Model\Page<Product>` — `count` (total across all pages), `next`,
`previous`, `results`. See [models.md#page](models.md#page).

**Throws** `SuqoError` and its subclasses — `AuthenticationError` on 401,
`RateLimitError` on 429, `ServerError` on 5xx, `NetworkError` on a transport failure
or timeout, `CancelledError` if the token was tripped. A network failure, a 429 or a
5xx is retried up to `maxRetries` times because this is a `GET`; a 401 or a 404 is
not.

```php
use Suqo\SuqoClient;

$suqo = new SuqoClient();

$page = $suqo->products->list(page: 1, pageSize: 50);

echo $page->count, PHP_EOL;               // total across all pages

foreach ($page->results as $product) {
    echo $product->productId, ' ', $product->name, PHP_EOL;
    echo '  vat ', $product->vat?->vatPercentage ?? 'n/a',
         ' subscribers ', $product->totalSubscribers, PHP_EOL;

    foreach ($product->plan as $plan) {
        echo '  plan ', $plan->planId, ' ', $plan->planName, PHP_EOL;
    }
}
```

`$page->next` is an absolute URL when the server sends one, and `null` on the last
page. Follow it yourself, or use `autoPaging()` and forget it exists.

## `autoPaging`

```php
public function autoPaging(
    ?int $page = null,
    ?int $pageSize = null,
    ?Cancellation $cancellation = null,
): Generator
```

`GET /api/v1/products/`, then each `next` link in turn. A lazy sequence of products
across every page.

Stops after `Pagination::MAX_PAGES` (10 000) with a `SuqoError` if the server never
stops advancing — a `next` link that repeats a page would otherwise iterate forever.
See [errors.md](errors.md#auto-paging-gave-up--base-suqoerror).

| Parameter | Type | Default | Notes |
| --- | --- | --- | --- |
| `page` | `?int` | `null` | The page to *start* from. |
| `pageSize` | `?int` | `null` | Sent as `page_size` on the first request; later pages take whatever the `next` link carries. |
| `cancellation` | `?Cancellation` | `null` | Checked at each page boundary as well as inside each attempt. |

**Returns** `Generator<int, Product>`. Nothing is accumulated: a page is fetched only
once you have exhausted the previous one, so breaking out of the loop early stops the
requests.

**Throws** the same set as `list()`, raised from the `foreach` rather than from the
call — the generator body does not run until first iteration.

```php
foreach ($suqo->products->autoPaging() as $product) {
    echo $product->name, PHP_EOL;
}
```

Every page — page 1 included — goes through the same absolute-URL `GET` and takes the
same retry policy. Mechanics in
[http.md#paginationautopage](http.md#paginationautopage).

## The record

`Suqo\Model\Product`. Every field is nullable: the SDK reads tolerantly, so a field
of an unexpected type reads as absent rather than failing the whole response.

| Property | Type | Wire key |
| --- | --- | --- |
| `productId` | `?string` | `product_id` |
| `name` | `?string` | `name` |
| `description` | `?string` | `description` |
| `type` | `?string` | `type` |
| `isActive` | `?bool` | `is_active` |
| `termsAndConditions` | `?string` | `terms_and_conditions` |
| `featuresAndBenefits` | `?string` | `features_and_benefits` |
| `vat` | `?ProductVat` | `vat` — an object; null only when the key is absent |
| `productImage` | `list<ProductImage>` | `product_image` |
| `plan` | `list<ProductPlan>` | `plan` |
| `totalSubscribers` | `?string` | `total_subscribers` — a decimal string |
| `createdAt` | `?string` | `created_at` |
| `updatedAt` | `?string` | `updated_at` |

`ProductVat`: `isVatActive` (`?bool`), `vatType` (`?string`, `inclusive` or
`exclusive`), `vatPercentage` (`?string`). All three are null when VAT is off,
which is the common case; the object itself is still present.

`ProductImage`: `image` (`?string`), `imageOrder` (`?int`).

`BillingPeriod`: `pbpId`, `intervalType`, `intervalCount` (`?int`), `label`,
`price`, `currency`, `isCurrent` / `isLimited` / `isArchived` (`?bool`),
`offers` (`list<Offer>`). **`pbpId` is the required input to
`subscriptions->create()`** — this is where you get it.

`Offer`: `id`, `discountAmount` (`?int`), `startsAt`, `validUntil`, `isActive`
(`?bool`).

`ProductPlan`: `planId`, `planName`, `description`, `billingPeriods`
(`list<BillingPeriod>`) — the scalars all
`?string`, all snake_case on the wire.

Anything the server adds beyond this list is still reachable:

```php
$raw = $product->toArray();      // decoded payload, wire names intact
```
