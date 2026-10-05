<?php

declare(strict_types=1);

/** @var \Suqo\Model\Subscription|null $subscription */
/** @var \Suqo\Exception\SuqoError|null $error */
/** @var string $id */

$status = $subscription?->status;
$statusLabel = $status instanceof \Suqo\Model\SubscriptionStatus ? $status->value : (string) $status;
?>
<h1>Subscription</h1>
<p class="lede"><code><?= e($id) ?></code></p>

<?php require __DIR__ . '/_error.php'; ?>

<?php if ($subscription !== null) { ?>
    <div class="panel">
        <dl class="kv">
            <dt>status</dt><dd><?= e($statusLabel) ?></dd>
            <dt>is_active</dt><dd><?= $subscription->isActive === null ? '—' : ($subscription->isActive ? 'true' : 'false') ?></dd>
            <dt>customer</dt><dd><?= e($subscription->customer?->fullName) ?> &lt;<?= e($subscription->customer?->email) ?>&gt;</dd>
            <dt>pbp_id</dt><dd><?= e($subscription->product?->pbpId) ?></dd>
            <dt>price</dt><dd><?= e($subscription->product?->price) ?> <?= e($subscription->product?->currency) ?></dd>
            <dt>current period</dt><dd><?= e($subscription->currentPeriodStart) ?> → <?= e($subscription->currentPeriodEnd) ?></dd>
            <dt>next billing</dt><dd><?= e($subscription->nextBillingCycle ?? '—') ?></dd>
        </dl>
    </div>

    <div class="panel">
        <h2 style="margin-top:0">Act on it</h2>
        <p class="muted" style="margin-top:0">Nothing here says whether it is recurring: match <code><?= e($subscription->product?->pbpId) ?></code> against <a href="/products">Products</a> and read that billing period's <code>interval_type</code>. A <code>one_time</code> purchase refuses all three of these with a 400.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
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
                <button type="submit">Renew — open a checkout session</button>
            </form>
        </div>
    </div>

    <h2>Raw <span class="muted">— still says <code>client</code></span></h2>
    <pre><?= e(json($subscription->toArray())) ?></pre>
<?php } ?>

<p><a href="/subscriptions">← back to the list</a></p>
