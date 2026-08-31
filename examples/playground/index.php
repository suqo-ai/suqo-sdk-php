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
use Suqo\Params\CreateSubscriptionParams;
use Suqo\Params\CustomerBilling;
use Suqo\Params\CustomerInput;
use Suqo\Params\CustomerShipping;
use Suqo\Params\UpdateBillingCycleParams;
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

    case 'GET /customers':
        // §10.3 — the resource exists, every operation refuses. Shown so the
        // behaviour is visible rather than surprising.
        [, $error] = attempt(static fn (SuqoClient $suqo) => $suqo->customers->list());

        view('customers', ['error' => $error]);

        // no break — view() exits.

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

