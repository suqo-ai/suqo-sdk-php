<?php

declare(strict_types=1);

/** @var list<\Suqo\Model\Product> $products */
/** @var \Suqo\Model\Page<\Suqo\Model\Product>|null $pageObject */
/** @var \Suqo\Exception\SuqoError|null $error */
/** @var int|null $page */
/** @var int $pageSize */
/** @var bool $walkAll */
?>
<h1>Products</h1>
<p class="lede">
    <?php if ($walkAll) { ?>
        Auto-paging: pages are fetched lazily as the loop consumes them, capped here at 200 records.
    <?php } else { ?>
        One page at a time, following the server's <code>next</code> link.
    <?php } ?>
</p>

<div class="panel">
    <form class="inline" method="get" action="/products">
        <div>
            <label for="page">page</label>
            <input id="page" name="page" type="number" min="1" value="<?= $page !== null ? (int) $page : '' ?>" <?= $walkAll ? 'disabled' : '' ?>>
        </div>
        <div>
            <label for="page_size">page_size</label>
            <input id="page_size" name="page_size" type="number" min="1" max="200" value="<?= (int) $pageSize ?>">
        </div>
        <button type="submit">List page</button>
        <a href="/products?all=1&amp;page_size=<?= (int) $pageSize ?>"><button class="ghost" type="button">Walk all pages</button></a>
    </form>
</div>

<?php require __DIR__ . '/_error.php'; ?>

<?php if ($pageObject !== null) { ?>
    <div class="panel stats">
        <div class="stat"><b><?= (int) $pageObject->count ?></b><span>count</span></div>
        <div class="stat"><b><?= count($pageObject->results) ?></b><span>on this page</span></div>
        <div class="stat"><b><?= $pageObject->next !== null ? 'yes' : 'no' ?></b><span>has next</span></div>
    </div>
<?php } ?>

<?php if ($products !== []) { ?>
    <div class="panel scroll">
        <table>
            <tr>
                <th>product_id</th><th>name</th><th>type</th><th>active</th>
                <th>vat</th><th>subscribers</th><th>plans</th>
            </tr>
            <?php foreach ($products as $product) { ?>
                <tr>
                    <td class="num"><?= e($product->productId) ?></td>
                    <td><?= e($product->name) ?></td>
                    <td><?= e($product->type) ?></td>
                    <td><?= $product->isActive === null ? '—' : ($product->isActive ? 'yes' : 'no') ?></td>
                    <td class="num"><?= $product->vat?->isVatActive === true
                        ? e(($product->vat->vatPercentage ?? '?') . '%')
                        : '<span class="muted">none</span>' ?></td>
                    <td class="num"><?= e($product->totalSubscribers) ?></td>
                    <td>
                        <?php foreach ($product->plan as $plan) { ?>
                            <div><code><?= e($plan->planId) ?></code> <?= e($plan->planName) ?></div>
                            <?php foreach ($plan->billingPeriods as $period) { ?>
                                <div class="muted" style="padding-left:12px">
                                    <code><?= e($period->pbpId) ?></code>
                                    <?= e($period->label) ?>
                                    <?= e($period->price) ?> <?= e($period->currency) ?>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>

    <?php if (!$walkAll && $pageObject !== null) { ?>
        <div class="pager">
            <?php if ($pageObject->previous !== null) { ?>
                <a href="/products?page=<?= max(1, ($page ?? 1) - 1) ?>&amp;page_size=<?= (int) $pageSize ?>">Previous</a>
            <?php } ?>
            <?php if ($pageObject->next !== null) { ?>
                <a href="/products?page=<?= ($page ?? 1) + 1 ?>&amp;page_size=<?= (int) $pageSize ?>">Next</a>
            <?php } ?>
            <span class="muted">server links: <code><?= e($pageObject->next ?? 'null') ?></code></span>
        </div>
    <?php } ?>

    <h2>First record, raw</h2>
    <pre><?= e(json($products[0]->toArray())) ?></pre>
<?php } elseif ($error === null) { ?>
    <div class="panel muted">No products visible to this key.</div>
<?php } ?>
