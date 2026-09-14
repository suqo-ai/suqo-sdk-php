# Models and params

Two families. `Suqo\Params\*` are what you build and send; `Suqo\Model\*` are what
you read back. A few types appear in both directions, because openapi reuses the
schema.

## The shared shape

Every read model extends `Suqo\Model\Model`, has a **private** constructor, and is
built by a static `fromWire()`. You never construct one — the SDK does, from a
decoded response.

Every params object is `final`, has a public constructor taking named arguments, and
serialises through `toWire()`.

Reads are tolerant: a field of an unexpected type reads as absent rather than
failing the whole response, so a server-side addition or loosening cannot break an
existing client. That is why nearly every model property is nullable.

### `Model::toArray`

```php
public function toArray(): array
```

The complete decoded payload, **with wire names intact**. This is the
forward-compatibility escape hatch: a field the SDK does not yet type is still
reachable, and a record that arrived as `client` still says `client` here even
though the typed accessor is `customer`.

```php
$product->toArray()['some_new_field'] ?? null;
$subscription->toArray()['client']['email'] ?? null;   // same as ->customer?->email
```

Available on every model, page included.

## Pages

### `Page`

`Suqo\Model\Page<T>` — the page shape, and the only model with a **public**
constructor (so you can build one in a test).

```php
public function __construct(
    public readonly int $count,
    public readonly ?string $next,
    public readonly ?string $previous,
    public readonly array $results,
    array $wire = [],
)
```

| Property | Type | Notes |
| --- | --- | --- |
| `count` | `int` | Total across all pages, not the size of `results`. `0` when absent. |
| `next` | `?string` | Absolute URL, or `null` on the last page. |
| `previous` | `?string` | Absolute URL, or `null` on the first page. |
| `results` | `list<T>` | The records on this page. |

#### `Page::fromWire`

```php
public static function fromWire(array $wire, callable $factory): self
```

| Parameter | Type | Notes |
| --- | --- | --- |
| `wire` | `array<string, mixed>` | A decoded page object. |
| `factory` | `callable(array): R` | Builds one record; also where the read-direction rename happens. |

`count` accepts a JSON number or a numeric string. Non-object entries in `results`
are dropped rather than yielding a broken record.

```php
use Suqo\Model\Page;
use Suqo\Model\Product;

$page = Page::fromWire($decoded, static fn (array $r): Product => Product::fromWire($r));
```

### `SubscriptionPage`

`Suqo\Model\SubscriptionPage extends Page<Subscription>` — a page plus four
account-level counters, each `?int` and each `null` when the server omits it.

| Property | Wire key |
| --- | --- |
| `totalSubscriptions` | `total_subscriptions` |
| `activeSubscriptions` | `active_subscriptions` |
| `dueSubscriptions` | `due_subscriptions` |
| `inactiveSubscriptions` | `inactive_subscriptions` |

Its constructor is public, taking `count, next, previous, results` first, then the
four counters, then `$wire`.

#### `SubscriptionPage::fromSubscriptionsWire`

```php
public static function fromSubscriptionsWire(array $wire): self
```

Named apart from `Page::fromWire()` because it takes no factory — it always builds
`Subscription` records. Counters accept a JSON number or a numeric string.

## Record models

Each has one static factory, `fromWire(array $wire): self`, plus readonly
properties and `toArray()`.

| Model | Properties |
| --- | --- |
| `Product` | `productId`, `name`, `description`, `type`, `isActive` (`?bool`), `termsAndConditions`, `featuresAndBenefits`, `vat` (`?ProductVat`), `productImage` (`list<ProductImage>`), `plan` (`list<ProductPlan>`), `totalSubscribers`, `createdAt`, `updatedAt` |
| `ProductVat` | `isVatActive` (`?bool`), `vatType`, `vatPercentage` |
| `ProductImage` | `image`, `imageOrder` (`?int`) |
| `ProductPlan` | `planId`, `planName`, `description`, `billingPeriods` (`list<BillingPeriod>`) |
| `BillingPeriod` | `pbpId`, `intervalType`, `intervalCount` (`?int`), `label`, `price`, `currency`, `isCurrent` / `isLimited` / `isArchived` (`?bool`), `offers` (`list<Offer>`) |
| `Offer` | `id`, `discountAmount` (`?int`), `startsAt`, `validUntil`, `isActive` (`?bool`) |
| `Subscription` | `subscriptionId`, `status` (`SubscriptionStatus\|string\|null`), `isActive` (`?bool`), `customer` (`?SubscriptionCustomer`, wire `client`), `product` (`?SubscriptionProduct`), `currentPeriodStart`, `currentPeriodEnd`, `nextBillingCycle`, `createdAt` |
| `SubscriptionCustomer` | `phone`, `fullName`, `email`, `address`, `billing`, `shipping` |
| `SubscriptionCustomerBilling` | `businessName`, `email`, `address`, `panVat` |
| `SubscriptionCustomerShipping` | `phone`, `fullName`, `email`, `address` |
| `SubscriptionProduct` | `productId`, `name`, `planName`, `pbpId`, `label`, `price`, `currency` |
| `Customer` | `id` (a `cus_…` public id, not an integer), `buyerPhone`, `buyerEmail`, `fullName`, `address`, `createdAt` |
| `CreateSubscriptionResponse` | `subscriptionId`, `pbpId`, `status` (`SubscriptionStatus\|string\|null`), `checkoutUrl`, `nextBillingCycle`, `createdAt` |
| `MessageResponse` | `message` (`string`, non-null, `''` when absent) |

Unless noted, every property is `?string`, and every wire key is the snake_case form
of the property name. The renames worth memorising: `customer` ⇄ `client`, and
`kycStatus` ⇄ `status_code` on `KycRequiredError`.

`CreateSubscriptionResponse::$customer` is the *write* shape read back
(`Params\CustomerInput`), not `SubscriptionCustomer`, because openapi declares the
201 body as the same schema as the request.

`Customer` is real, but no operation returns one yet — see
[customers.md](customers.md).

### The two billing shapes

Billing has different keys in each direction, and the SDK reflects that rather than
papering over it:

| Direction | Type | Property | Wire key |
| --- | --- | --- | --- |
| write | `Params\CustomerBilling` | `billingBusinessName` | `billing_business_name` |
| read | `Model\SubscriptionCustomerBilling` | `businessName` | `business_name` |

So you send `billingEmail` and read back `email`. Same for address and PAN/VAT.

### Decimals and dates are strings

`price`, `vatPercentage`, `totalSubscribers` and every timestamp or date are `string`, end to
end, and are never parsed into a float inside the SDK. Parse at your own boundary:

```php
$total = bcmul($subscription->product->price, '2', 2);
```

A numeric wire value is stringified rather than rejected, but the API is specified
to send strings.

## `SubscriptionStatus`

```php
enum SubscriptionStatus: string
{
    case PendingCheckout     = 'pending_checkout';
    case Active              = 'active';
    case Due                 = 'due';
    case Cancelled           = 'cancelled';
    case PendingCancellation = 'pending_cancellation';
    case Inactive            = 'inactive';
}
```

### `SubscriptionStatus::parse`

```php
public static function parse(mixed $value): self|string|null
```

A recognised value as a case; an unrecognised one as the raw string; an absent or
non-string value as `null`. That union is why `Subscription::$status` is typed
`SubscriptionStatus|string|null`: a status the server adds later surfaces instead of
failing deserialisation.

```php
use Suqo\Model\SubscriptionStatus;

SubscriptionStatus::parse('active');      // SubscriptionStatus::Active
SubscriptionStatus::parse('paused');      // "paused"
SubscriptionStatus::parse(null);          // null
```

Always narrow with `instanceof` before matching — see
[subscriptions.md#subscription-status](subscriptions.md#subscription-status).

## Params objects

All five take named arguments and end with `array $extra = []`, which is merged into
the serialised object **under wire names, verbatim**. Use it to send a field the SDK
does not yet type:

```php
new UpdateBillingCycleParams(
    subscriptionId: '3fa85f64-5717-4562-b3fc-2c963f66afa6',
    nextBillingCycle: '2026-09-03',
    extra: ['reason' => 'customer request'],
);
```

`extra` is merged with `+`, so a declared key always wins over an `extra` key of the
same name.

### `CreateSubscriptionParams`

```php
public function __construct(
    public readonly string $pbpId,
    public readonly CustomerInput $customer,
    public readonly ?string $returnUrl = null,
    public readonly array $extra = [],
)
```

| Parameter | Type | Required | Wire key |
| --- | --- | --- | --- |
| `pbpId` | `string` | yes | `pbp_id` |
| `customer` | `CustomerInput` | yes | **`client`** |
| `returnUrl` | `?string` | no | `return_url` — omitted when null |
| `extra` | `array` | no | merged verbatim |

`toWire(): array` performs the write-direction rename. There is no `fromWire()` —
the response side is `Model\CreateSubscriptionResponse`.

### `CustomerInput`

```php
public function __construct(
    public readonly string $phone,
    public readonly string $fullName,
    public readonly string $email,
    public readonly ?string $address = null,
    public readonly ?CustomerBilling $billing = null,
    public readonly ?CustomerShipping $shipping = null,
    public readonly array $extra = [],
)
```

Wire keys: `phone`, `full_name`, `email`, `address`, `billing`, `shipping`. The last
three are omitted from the body when null.

`toWire(): array` serialises nested objects too.

`fromWire(array): self` reads the shape back. It exists because openapi once
declared the 201 body as a reuse of this request schema; it no longer does — the
response is its own shape ({@see CreateSubscriptionResponse}) and carries no
customer at all. Nothing in the SDK calls `fromWire()` today, and it is kept only
so callers who stored a serialised payload can rebuild one. It is tolerant: a
missing or non-string required field becomes `''`, and a nested value that is not
an object becomes `null`.

### `CustomerBilling`

```php
public function __construct(
    public readonly string $billingBusinessName,
    public readonly string $billingEmail,
    public readonly string $billingAddress,
    public readonly ?string $billingPanVat = null,
    public readonly array $extra = [],
)
```

Wire keys keep the `billing_` prefix even though the object is already nested under
`billing`: `billing_business_name`, `billing_email`, `billing_address`,
`billing_pan_vat` (omitted when null). `toWire()` and `fromWire()` both present.

### `CustomerShipping`

```php
public function __construct(
    public readonly string $phone,
    public readonly string $fullName,
    public readonly string $email,
    public readonly ?string $address = null,
    public readonly array $extra = [],
)
```

Wire keys: `phone`, `full_name`, `email`, `address` (omitted when null). `toWire()`
and `fromWire()` both present.

### `UpdateBillingCycleParams`

```php
public function __construct(
    public readonly string $subscriptionId,
    public readonly string $nextBillingCycle,
    public readonly array $extra = [],
)
```

Both fields required. Wire keys `subscription_id` and `next_billing_cycle`.
`nextBillingCycle` is `YYYY-MM-DD` and stays a string. `toWire()` only.

### Inspecting what will be sent

`toWire()` is public, so you can see the exact body without making a request —
handy when a `ValidationError` names a wire key you did not expect:

```php
$params = new CreateSubscriptionParams(pbpId: 'pbp_3n9k2x', customer: $customer);

echo json_encode($params->toWire(), JSON_PRETTY_PRINT);
// {"pbp_id":"pbp_3n9k2x","client":{"phone":"…","full_name":"…","email":"…"}}
```
