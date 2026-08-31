<?php

declare(strict_types=1);

/**
 * Lazily walk every product, printing the fields the product list actually
 * carries. Note that price lives on a plan's billing point, not on the product.
 *
 * SUQO_API_KEY=su_test_key_… php examples/list_products.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Suqo\Exception\SuqoError;
use Suqo\SuqoClient;

$suqo = new SuqoClient();

try {
    foreach ($suqo->products->autoPaging(pageSize: 50) as $product) {
        printf(
            "%-38s %-24s %-7s vat %-7s subs %-5s plans %s\n",
            $product->productId ?? '-',
            $product->name ?? '-',
            $product->isActive === true ? 'active' : 'off',
            $product->vat ?? '-',
            $product->totalSubscribers ?? '-',
            implode(', ', array_map(
                static fn ($plan): string => $plan->planName ?? '-',
                $product->plan,
            )) ?: '-',
        );
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
