<?php

declare(strict_types=1);

/**
 * SUQO PHP SDK playground — front controller.
 *
 * Run it with:
 *   composer playground
 * or:
 *   php -S 127.0.0.1:8000 -t examples/playground examples/playground/index.php
 *
 * Then open http://127.0.0.1:8000 and paste an API key into the form. Nothing in
 * this project needs editing to switch keys or environments.
 */

require __DIR__ . '/bootstrap.php';

use Suqo\Config;
use Suqo\Exception\SuqoConfigError;
use Suqo\Model\WebhookEvent;
use Suqo\Params\CheckoutItem;
use Suqo\Params\CreateCheckoutSessionParams;
use Suqo\Params\CreateSubscriptionParams;
use Suqo\Params\CustomerBilling;
use Suqo\Params\CustomerCreateParams;
use Suqo\Params\CustomerInput;
use Suqo\Params\CustomerShipping;
use Suqo\Params\CustomerUpdateParams;
use Suqo\Params\UpdateBillingCycleParams;
use Suqo\Params\WebhookParams;
use Suqo\Params\WebhookUpdateParams;
use Suqo\SuqoClient;
use Suqo\Webhook;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rtrim(parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/', '/');
$route = $method . ' ' . ($path === '' ? '/' : $path);

// The webhook verifier is the one page that works without a key (§12).
if (!isConnected() && !in_array($route, ['GET /', 'POST /connect', 'GET /webhook', 'POST /webhook'], true)) {
    redirect('/');
}

switch ($route) {
    case 'GET /':
        if (!isConnected()) {
            view('connect');
        }

        view('dashboard', ['config' => client()->config]);

        // no break — view() exits.

    case 'POST /connect':
        assertCsrf();

        $key = post('api_key');
        $timeout = post('timeout');
        $retries = post('max_retries');

        try {
            // Config::resolve does the §4.2 validation offline: prefix check,
            // environment inference, no network involved.
            $config = Config::resolve(
                apiKey: $key,
                timeout: $timeout !== null ? (float) $timeout : null,
                maxRetries: $retries !== null ? (int) $retries : null,
            );
        } catch (SuqoConfigError $e) {
            flash('error', $e->getMessage());
            redirect('/');
        }

        $_SESSION[SESSION_KEY] = $config->apiKey;
        $_SESSION['suqo_timeout'] = $config->timeout;
        $_SESSION['suqo_retries'] = $config->maxRetries;

        flash('ok', sprintf(
            'Connected to %s (%s). The key is held in this browser session only.',
            $config->environment->value,
            $config->baseUrl,
        ));
        redirect('/');

    case 'POST /disconnect':
        assertCsrf();
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'Key discarded.');
        redirect('/');

    case 'GET /ping':
        // Smallest possible live call, to prove the key works.
        [$page, $error] = attempt(static fn (SuqoClient $suqo) => $suqo->products->list(pageSize: 1));

        if ($error === null && $page !== null) {
            flash('ok', sprintf('Reachable. %d product(s) visible to this key.', $page->count));
            redirect('/');
        }

        view('dashboard', ['config' => client()->config, 'error' => $error]);

        // no break — view() exits.

    case 'GET /products':
        $page = queryInt('page');
        $pageSize = queryInt('page_size') ?? 20;
        $walkAll = query('all') === '1';

        if ($walkAll) {
            [$products, $error] = attempt(static function (SuqoClient $suqo) use ($pageSize): array {
                $collected = [];

                // Lazy: pages are fetched only as this loop consumes them.
                foreach ($suqo->products->autoPaging(pageSize: $pageSize) as $product) {
                    $collected[] = $product;

                    if (count($collected) >= 200) {
                        break;
                    }
                }

                return $collected;
            });

            view('products', [
                'products' => $products ?? [],
                'pageObject' => null,
                'error' => $error,
                'page' => null,
                'pageSize' => $pageSize,
                'walkAll' => true,
            ]);
        }

        [$pageObject, $error] = attempt(
            static fn (SuqoClient $suqo) => $suqo->products->list(page: $page, pageSize: $pageSize),
        );

        view('products', [
            'products' => $pageObject?->results ?? [],
            'pageObject' => $pageObject,
            'error' => $error,
            'page' => $page,
            'pageSize' => $pageSize,
            'walkAll' => false,
        ]);

        // no break — view() exits.

    case 'GET /subscriptions':
        $page = queryInt('page');
        $pageSize = queryInt('page_size') ?? 20;

        [$pageObject, $error] = attempt(
            static fn (SuqoClient $suqo) => $suqo->subscriptions->list(page: $page, pageSize: $pageSize),
        );

        view('subscriptions', [
            'pageObject' => $pageObject,
            'error' => $error,
            'page' => $page,
            'pageSize' => $pageSize,
        ]);

        // no break — view() exits.

    case 'GET /subscriptions/new':
        view('create', ['result' => null, 'error' => null, 'sent' => null]);

        // no break — view() exits.

    case 'POST /subscriptions':
        assertCsrf();

        $pbpId = post('pbp_id');
        $phone = post('phone');
        $fullName = post('full_name');
        $email = post('email');

        if ($pbpId === null || $phone === null || $fullName === null || $email === null) {
            flash('error', 'pbp_id, phone, full_name and email are all required by the API.');
            redirect('/subscriptions/new');
        }

        $billing = null;
        if (post('billing_business_name') !== null) {
            $billing = new CustomerBilling(
                billingBusinessName: (string) post('billing_business_name'),
                billingEmail: (string) (post('billing_email') ?? $email),
                billingAddress: (string) (post('billing_address') ?? ''),
                billingPanVat: post('billing_pan_vat'),
            );
        }

        $shipping = null;
        if (post('shipping_full_name') !== null) {
            $shipping = new CustomerShipping(
                phone: (string) (post('shipping_phone') ?? $phone),
                fullName: (string) post('shipping_full_name'),
                email: (string) (post('shipping_email') ?? $email),
                address: post('shipping_address'),
            );
        }

        $params = new CreateSubscriptionParams(
            pbpId: $pbpId,
            customer: new CustomerInput(
                phone: $phone,
                fullName: $fullName,
                email: $email,
                address: post('address'),
                billing: $billing,
                shipping: $shipping,
            ),
            returnUrl: post('return_url'),
        );

        [$result, $error] = attempt(static fn (SuqoClient $suqo) => $suqo->subscriptions->create($params));

        view('create', ['result' => $result, 'error' => $error, 'sent' => $params->toWire()]);

        // no break — view() exits.

    case 'POST /subscriptions/cancel':
        assertCsrf();

        $id = post('subscription_id');

        if ($id === null) {
            flash('error', 'A subscription id is required.');
            redirect('/subscriptions');
        }

        [$result, $error] = attempt(static fn (SuqoClient $suqo) => $suqo->subscriptions->cancel($id));

        if ($error !== null) {
            flash('error', describeError($error));
        } elseif ($result !== null) {
            flash('ok', $result->message);
        }

        redirect('/subscriptions');

    case 'POST /subscriptions/billing-cycle':
        assertCsrf();

        $id = post('subscription_id');
        $next = post('next_billing_cycle');

        if ($id === null || $next === null) {
            flash('error', 'Both a subscription id and a next billing cycle date are required.');
            redirect('/subscriptions');
        }

        $params = new UpdateBillingCycleParams(subscriptionId: $id, nextBillingCycle: $next);

        [$result, $error] = attempt(
            static fn (SuqoClient $suqo) => $suqo->subscriptions->updateBillingCycle($params),
        );

        if ($error !== null) {
            flash('error', describeError($error));
        } elseif ($result !== null) {
            flash('ok', $result->message);
        }

        redirect('/subscriptions');

    case 'GET /subscriptions/view':
        $id = query('id');

        if ($id === null) {
            flash('error', 'A subscription id is required.');
            redirect('/subscriptions');
        }

        [$subscription, $error] = attempt(static fn (SuqoClient $suqo) => $suqo->subscriptions->read($id));

        view('subscription', ['subscription' => $subscription, 'error' => $error, 'id' => $id]);

        // no break — view() exits.

    case 'POST /subscriptions/resume':
        assertCsrf();

        $id = post('subscription_id');

        if ($id === null) {
            flash('error', 'A subscription id is required.');
            redirect('/subscriptions');
        }

        [$result, $error] = attempt(static fn (SuqoClient $suqo) => $suqo->subscriptions->resume($id));

        if ($error !== null) {
            // An illegal transition answers 400 with a bare list of messages,
            // which the mapper surfaces as the error message.
            flash('error', describeError($error));
        } elseif ($result !== null) {
            flash('ok', $result->message);
        }

        redirect('/subscriptions');

    case 'POST /subscriptions/renew':
        assertCsrf();

        $id = post('subscription_id');

        if ($id === null) {
            flash('error', 'A subscription id is required.');
            redirect('/subscriptions');
        }

        [$session, $error] = attempt(static fn (SuqoClient $suqo) => $suqo->subscriptions->renew($id));

        if ($error !== null) {
            flash('error', describeError($error));
            redirect('/subscriptions');
        }

        flash('ok', sprintf(
            'Renewal session %s opened — send the buyer to %s',
            $session?->publicId ?? '-',
            $session?->checkoutUrl ?? '-',
        ));
        redirect('/checkout?id=' . urlencode($session?->publicId ?? ''));

    case 'GET /customers':
        [$page, $error] = attempt(static fn (SuqoClient $suqo) => $suqo->customers->list());

        view('customers', ['page' => $page, 'error' => $error]);

        // no break — view() exits.

    case 'POST /customers':
        assertCsrf();

        $email = post('email');

        if ($email === null) {
            flash('error', 'An email is required: it identifies the buyer.');
            redirect('/customers');
        }

        $params = new CustomerCreateParams(
            email: $email,
            phone: post('phone'),
            fullName: post('full_name'),
            address: post('address'),
        );

        [$customer, $error] = attempt(static fn (SuqoClient $suqo) => $suqo->customers->create($params));

        if ($error !== null) {
            flash('error', describeError($error));
        } elseif ($customer !== null) {
            // An email or phone already on the account is corrected and answered
            // 200 rather than 201 — the upsert that makes a retry safe.
            flash('ok', sprintf('Recorded %s (%s).', $customer->id ?? '-', $customer->buyerEmail ?? '-'));
        }

        redirect('/customers');

    case 'POST /customers/update':
        assertCsrf();

        $id = post('customer_id');

        if ($id === null) {
            flash('error', 'A customer id (cus_…) is required.');
            redirect('/customers');
        }

        // '' is a real value here — it clears the field — so the blank-to-null
        // helper is bypassed deliberately for the clearable fields.
        $clearable = static function (string $name): ?string {
            $value = $_POST[$name] ?? null;

            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        };

        $clear = $_POST['clear'] ?? [];
        $clear = is_array($clear) ? $clear : [];

        $params = new CustomerUpdateParams(
            fullName: in_array('full_name', $clear, true) ? '' : $clearable('full_name'),
            email: $clearable('email'),
            address: in_array('address', $clear, true) ? '' : $clearable('address'),
        );

        [$customer, $error] = attempt(
            static fn (SuqoClient $suqo) => $suqo->customers->update($id, $params),
        );

        if ($error !== null) {
            flash('error', describeError($error));
        } elseif ($customer !== null) {
            flash('ok', sprintf('Updated %s. Body sent: %s', $customer->id ?? '-', json_encode($params->toWire())));
        }

        redirect('/customers');

    case 'GET /checkout':
        $id = query('id');
        $detail = null;
        $error = null;

        if ($id !== null) {
            [$detail, $error] = attempt(static fn (SuqoClient $suqo) => $suqo->checkoutSessions->read($id));
        }

        view('checkout', ['session' => null, 'detail' => $detail, 'error' => $error, 'sent' => null, 'id' => $id]);

        // no break — view() exits.

    case 'POST /checkout':
        assertCsrf();

        $items = [];
        $pbpId = post('pbp_id');

        if ($pbpId !== null) {
            $items[] = CheckoutItem::billingPeriod($pbpId);
        }

        $inlineName = post('item_name');
        $inlineAmount = post('item_amount');

        if ($inlineName !== null && $inlineAmount !== null) {
            $items[] = CheckoutItem::inline(
                name: $inlineName,
                amount: $inlineAmount,
                intervalType: post('item_interval_type') ?? 'one_time',
                intervalCount: (int) (post('item_interval_count') ?? '0'),
                discountAmount: post('item_discount_amount'),
            );
        }

        $returnUrl = post('return_url');

        if ($items === [] || $returnUrl === null) {
            flash('error', 'At least one item and a return_url are required.');
            redirect('/checkout');
        }

        $params = new CreateCheckoutSessionParams(
            items: $items,
            returnUrl: $returnUrl,
            customerId: post('customer_id'),
        );

        [$session, $error] = attempt(
            static fn (SuqoClient $suqo) => $suqo->checkoutSessions->create($params),
        );

        $detail = null;

        if ($session !== null && $session->publicId !== null) {
            // Read it straight back: a session is servable only while it is open.
            [$detail] = attempt(
                static fn (SuqoClient $suqo) => $suqo->checkoutSessions->read((string) $session->publicId),
            );
        }

        view('checkout', [
            'session' => $session,
            'detail' => $detail,
            'error' => $error,
            'sent' => $params->toWire(),
            'id' => $session?->publicId,
        ]);

        // no break — view() exits.

    case 'GET /endpoints':
        [$webhooks, $error] = attempt(static fn (SuqoClient $suqo) => $suqo->webhooks->list());
        [$secret] = attempt(static fn (SuqoClient $suqo) => $suqo->webhooks->secret());

        view('endpoints', ['webhooks' => $webhooks ?? [], 'secret' => $secret, 'error' => $error]);

        // no break — view() exits.

    case 'POST /endpoints':
        assertCsrf();

        $event = post('event');
        $endpointUrl = post('endpoint_url');

        if ($event === null || $endpointUrl === null) {
            flash('error', 'An event and an https endpoint URL are both required.');
            redirect('/endpoints');
        }

        $params = new WebhookParams(
            event: WebhookEvent::parse($event) ?? $event,
            endpointUrl: $endpointUrl,
        );

        [$webhook, $error] = attempt(static fn (SuqoClient $suqo) => $suqo->webhooks->create($params));

        if ($error !== null) {
            // https only, must resolve, and must resolve only to public
            // addresses — localhost and a split-horizon host are both refused.
            flash('error', describeError($error));
        } elseif ($webhook !== null) {
            flash('ok', sprintf('Registered %s for %s.', $webhook->id ?? '-', $endpointUrl));
        }

        redirect('/endpoints');

    case 'POST /endpoints/toggle':
        assertCsrf();

        $id = post('webhook_id');
        $active = post('is_active') === '1';

        if ($id === null) {
            flash('error', 'A webhook id (whk_…) is required.');
            redirect('/endpoints');
        }

        // The whole body is {"is_active": …}: PATCH sends only what was set.
        $params = new WebhookUpdateParams(isActive: $active);

        [$webhook, $error] = attempt(
            static fn (SuqoClient $suqo) => $suqo->webhooks->update($id, $params),
        );

        if ($error !== null) {
            flash('error', describeError($error));
        } elseif ($webhook !== null) {
            flash('ok', sprintf('%s is now %s.', $webhook->id ?? '-', ($webhook->isActive ?? false) ? 'active' : 'paused'));
        }

        redirect('/endpoints');

    case 'POST /endpoints/test':
        assertCsrf();

        $id = post('webhook_id');

        if ($id === null) {
            flash('error', 'A webhook id (whk_…) is required.');
            redirect('/endpoints');
        }

        [$result, $error] = attempt(static fn (SuqoClient $suqo) => $suqo->webhooks->testDelivery($id));

        if ($error !== null) {
            flash('error', describeError($error));
        } elseif ($result !== null) {
            // 202 means queued, not delivered.
            flash('ok', $result->detail);
        }

        redirect('/endpoints');

    case 'POST /endpoints/delete':
        assertCsrf();

        $id = post('webhook_id');

        if ($id === null) {
            flash('error', 'A webhook id (whk_…) is required.');
            redirect('/endpoints');
        }

        [, $error] = attempt(static function (SuqoClient $suqo) use ($id): bool {
            $suqo->webhooks->delete($id);

            return true;
        });

        if ($error !== null) {
            flash('error', describeError($error));
        } else {
            flash('ok', sprintf('Deleted %s. Past delivery records are kept.', $id));
        }

        redirect('/endpoints');

    case 'GET /webhook':
        view('webhook', ['verified' => null, 'input' => []]);

        // no break — view() exits.

    case 'POST /webhook':
        assertCsrf();

        $rawBody = $_POST['raw_body'] ?? '';
        $rawBody = is_string($rawBody) ? $rawBody : '';
        $secret = post('secret') ?? '';
        $signature = post('signature');
        $timestamp = post('timestamp');
        $maxAge = post('max_age');

        // Convenience: sign the pasted bytes the way a sender would, so the page
        // is demonstrable without a real delivery.
        if (post('action') === 'sign') {
            $timestamp = (string) time();
            $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
            flash('info', 'Signed the body below with the secret at the current timestamp.');
        }

        $verified = Webhook::verify(
            rawBody: $rawBody,
            signature: $signature,
            timestamp: $timestamp,
            secret: $secret,
            maxAge: $maxAge !== null && ctype_digit($maxAge) ? (int) $maxAge : 300,
        );

        view('webhook', [
            'verified' => $verified,
            'input' => [
                'raw_body' => $rawBody,
                'secret' => $secret,
                'signature' => $signature,
                'timestamp' => $timestamp,
                'max_age' => $maxAge,
            ],
        ]);

        // no break — view() exits.

    default:
        http_response_code(404);
        view('notfound');
}

