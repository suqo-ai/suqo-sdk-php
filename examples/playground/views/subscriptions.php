<?php

declare(strict_types=1);

/** @var \Suqo\Model\SubscriptionPage|null $pageObject */
/** @var \Suqo\Exception\SuqoError|null $error */
/** @var int|null $page */
/** @var int $pageSize */

$results = $pageObject?->results ?? [];
?>
<h1>Subscriptions</h1>
<p class="lede">The wire says <code>client</code>; the surface says <code>customer</code> (§3). Raw payloads below still say <code>client</code>.</p>

<div class="panel">
    <form class="inline" method="get" action="/subscriptions">
        <div>
            <label for="page">page</label>
            <input id="page" name="page" type="number" min="1" value="<?= $page !== null ? (int) $page : '' ?>">
        </div>
        <div>
            <label for="page_size">page_size</label>
            <input id="page_size" name="page_size" type="number" min="1" max="200" value="<?= (int) $pageSize ?>">
        </div>
        <button type="submit">List</button>
    </form>
</div>

<?php require __DIR__ . '/_error.php'; ?>

<?php if ($pageObject !== null) { ?>
    <div class="panel stats">
        <div class="stat"><b><?= (int) $pageObject->count ?></b><span>count</span></div>
        <div class="stat"><b><?= $pageObject->totalSubscriptions ?? '—' ?></b><span>total_subscriptions</span></div>
        <div class="stat"><b><?= $pageObject->activeSubscriptions ?? '—' ?></b><span>active</span></div>
        <div class="stat"><b><?= $pageObject->dueSubscriptions ?? '—' ?></b><span>due</span></div>
        <div class="stat"><b><?= $pageObject->inactiveSubscriptions ?? '—' ?></b><span>inactive</span></div>
    </div>
<?php } ?>

<?php if ($results !== []) { ?>
    <div class="panel scroll">
        <table>
            <tr>
                <th>subscription_id</th><th>status</th><th>customer</th>
                <th>product</th><th>price</th><th>next billing</th><th></th>
            </tr>
            <?php foreach ($results as $subscription) {
                $status = $subscription->status;
                $statusLabel = $status instanceof \Suqo\Model\SubscriptionStatus ? $status->value : (string) $status; ?>
                <tr>
                    <td class="num"><?= e($subscription->subscriptionId) ?></td>
                    <td>
                        <span class="pill"><?= e($statusLabel) ?></span>
                        <?php if (!$status instanceof \Suqo\Model\SubscriptionStatus && $status !== null) { ?>
                            <div class="muted" style="font-size:11px">unrecognised — preserved (§9.3)</div>
                        <?php } ?>
                    </td>
                    <td>
                        <?= e($subscription->customer?->fullName) ?><br>
                        <span class="muted"><?= e($subscription->customer?->email) ?></span>
                    </td>
                    <td>
                        <?= e($subscription->product?->name) ?><br>
                        <span class="muted"><code><?= e($subscription->product?->pbpId) ?></code></span>
                    </td>
                    <td class="num"><?= e($subscription->product?->price) ?> <?= e($subscription->product?->currency) ?></td>
                    <td class="num"><?= e($subscription->nextBillingCycle) ?></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <a href="/subscriptions/view?id=<?= urlencode((string) $subscription->subscriptionId) ?>"><button class="ghost" type="button">Open</button></a>
                            <form method="post" action="/subscriptions/cancel" onsubmit="return confirm('Cancel this subscription?')" style="width:auto">
                                <?= csrfField() ?>
                                <input type="hidden" name="subscription_id" value="<?= e($subscription->subscriptionId) ?>">
                                <button class="ghost" type="submit">Cancel</button>
                            </form>
                            <form method="post" action="/subscriptions/resume" style="width:auto">
                                <?= csrfField() ?>
                                <input type="hidden" name="subscription_id" value="<?= e($subscription->subscriptionId) ?>">
                                <button class="ghost" type="submit">Resume</button>
                            </form>
                            <form method="post" action="/subscriptions/renew" style="width:auto">
                                <?= csrfField() ?>
                                <input type="hidden" name="subscription_id" value="<?= e($subscription->subscriptionId) ?>">
                                <button class="ghost" type="submit">Renew</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>

    <div class="pager">
        <?php if ($pageObject?->previous !== null) { ?>
            <a href="/subscriptions?page=<?= max(1, ($page ?? 1) - 1) ?>&amp;page_size=<?= (int) $pageSize ?>">Previous</a>
        <?php } ?>
        <?php if ($pageObject?->next !== null) { ?>
            <a href="/subscriptions?page=<?= ($page ?? 1) + 1 ?>&amp;page_size=<?= (int) $pageSize ?>">Next</a>
        <?php } ?>
    </div>

    <h2>First record, raw <span class="muted">— note the <code>client</code> key</span></h2>
    <pre><?= e(json($results[0]->toArray())) ?></pre>
<?php } elseif ($error === null) { ?>
    <div class="panel muted">No subscriptions on this page.</div>
<?php } ?>

<h2>Move a billing cycle</h2>
<div class="panel">
    <form method="post" action="/subscriptions/billing-cycle">
        <?= csrfField() ?>
        <div class="row">
            <div>
                <label for="bc_id">subscription_id</label>
                <input id="bc_id" name="subscription_id" required placeholder="3fa85f64-…">
            </div>
            <div>
                <label for="bc_date">next_billing_cycle</label>
                <input id="bc_date" name="next_billing_cycle" type="date" required>
            </div>
        </div>
        <div class="actions"><button type="submit">Update billing cycle</button></div>
    </form>
</div>

<h2>By id</h2>
<div class="panel">
    <form class="inline" method="get" action="/subscriptions/view">
        <div style="flex:1">
            <label for="open_id">subscription_id</label>
            <input id="open_id" name="id" required placeholder="3fa85f64-…">
        </div>
        <button type="submit">Open</button>
    </form>
    <p class="muted" style="margin-bottom:0">Cancel, resume and renew live on that page. Cancellation is scheduled for the end of the current period and resume undoes it until then; both are safe to repeat. Renew opens a checkout session for the next payment on the same customer and billing period.</p>
</div>
