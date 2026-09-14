<?php

declare(strict_types=1);

/**
 * Lazily walk every product, printing the fields the product list actually
 * carries — including each plan's billing points, which is where `pbpId` and
 * `price` live. `pbpId` is what `subscriptions->create()` needs, so this is the
 * script to run first.
 *
 * SUQO_API_KEY=su_test_key_… php examples/list_products.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Suqo\Exception\SuqoError;
use Suqo\SuqoClient;

$suqo = new SuqoClient();

try {
    foreach ($suqo->products->autoPaging(pageSize: 50) as $product) {
        // `vat` is an object; its members are null when VAT is switched off,
        // which is the common case.
        $vat = $product->vat?->isVatActive === true
            ? ($product->vat->vatPercentage ?? '?') . '% ' . ($product->vat->vatType ?? '')
            : 'none';

        printf(
            "%-38s %-24s %-7s vat %-16s subs %-5s images %d\n",
            $product->productId ?? '-',
            $product->name ?? '-',
            $product->isActive === true ? 'active' : 'off',
            $vat,
            $product->totalSubscribers ?? '-',
            count($product->productImage),
        );

        foreach ($product->plan as $plan) {
            printf("    plan %-10s %s\n", $plan->planId ?? '-', $plan->planName ?: '(unnamed)');

            foreach ($plan->billingPeriods as $period) {
                printf(
                    "        %-18s %-10s %8s %-4s%s%s\n",
                    $period->pbpId ?? '-',
                    $period->label ?? '-',
                    $period->price ?? '-',
                    $period->currency ?? '-',
                    $period->isCurrent === true ? ' current' : '',
                    $period->offers === [] ? '' : ' ' . count($period->offers) . ' offer(s)',
                );
            }
        }
    }
} catch (SuqoError $e) {
    fwrite(STDERR, sprintf(
        "suqo error %d (request %s): %s\n",
        $e->status,
        $e->requestId,
        $e->getMessage(),
    ));
    exit(1);
}
