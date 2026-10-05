<?php

declare(strict_types=1);

/** @var \Suqo\Model\CheckoutSession|null $session */
/** @var \Suqo\Model\CheckoutSessionDetail|null $detail */
/** @var \Suqo\Exception\SuqoError|null $error */
/** @var array<string, mixed>|null $sent */
/** @var string|null $id */
?>
<h1>Checkout sessions</h1>
<p class="lede">One payment for up to ten billing periods, or for lines you price yourself. The one rate-limited endpoint — 20 a minute — and a write, so the SDK never retries it for you.</p>

<?php require __DIR__ . '/_error.php'; ?>

<?php if ($session !== null) { ?>
    <div class="panel">
        <h2 style="margin-top:0">Opened</h2>
        <dl class="kv">
            <dt>public_id</dt><dd><?= e($session->publicId) ?></dd>
            <dt>expires_at</dt><dd><?= e($session->expiresAt) ?></dd>
            <dt>checkout_url</dt><dd><a href="<?= e($session->checkoutUrl) ?>" target="_blank" rel="noopener"><?= e($session->checkoutUrl) ?></a></dd>
        </dl>
        <p class="muted" style="margin:12px 0 0">Send that URL to the buyer. The return to <code>return_url</code> is not proof of payment — the real outcome arrives on the <code>checkout.succeeded</code> / <code>checkout.failed</code> webhooks.</p>
    </div>
<?php } ?>

<?php if ($sent !== null) { ?>
    <h2>Body sent <span class="muted">— note that each item carries one shape or the other</span></h2>
    <pre><?= e(json($sent)) ?></pre>
<?php } ?>

<?php if ($detail !== null) { ?>
    <h2>Read back <span class="muted">— served only while the session is open</span></h2>
    <div class="panel">
        <dl class="kv">
            <dt>public_id</dt><dd><?= e($detail->publicId) ?></dd>
            <dt>customer_id</dt><dd><?= e($detail->customerId ?? '— (OTP at checkout)') ?></dd>
            <dt>seller</dt><dd><?= e($detail->sellerDetails?->businessName ?? '—') ?></dd>
            <dt>return_url</dt><dd><?= e($detail->returnUrl) ?></dd>
            <dt>expires_at</dt><dd><?= e($detail->expiresAt) ?></dd>
            <dt>completed_at</dt><dd><?= e($detail->completedAt ?? '— (still open)') ?></dd>
            <dt>is_expired</dt><dd><?= $detail->isExpired === null ? '—' : ($detail->isExpired ? 'true' : 'false') ?></dd>
        </dl>
    </div>
    <h2>line_items, raw</h2>
    <pre><?= e(json($detail->lineItems)) ?></pre>
<?php } ?>

<h2>Open a session</h2>
<div class="panel">
    <form method="post" action="/checkout">
        <?= csrfField() ?>
        <p class="muted" style="margin-top:0">An item is <strong>either</strong> a billing period <strong>or</strong> an inline line — the API rejects a mixture, so <code>CheckoutItem</code> has one named constructor per shape. Fill either block, or both, to send two items.</p>
        <div class="row">
            <div>
                <label for="pbp_id">pbp_id <span class="muted">— from Products</span></label>
                <input id="pbp_id" name="pbp_id" placeholder="pbp_3n9k2x">
            </div>
            <div>
                <label for="return_url">return_url <span class="muted">— required</span></label>
                <input id="return_url" name="return_url" required value="https://merchant.example.com/orders/1234">
            </div>
            <div>
                <label for="customer_id">customer_id <span class="muted">— blank = OTP at checkout</span></label>
                <input id="customer_id" name="customer_id" placeholder="cus_…">
            </div>
        </div>

        <h2 style="margin-top:18px">Inline item <span class="muted">— partner-priced, no billing period behind it</span></h2>
        <div class="row">
            <div>
                <label for="item_name">name</label>
                <input id="item_name" name="item_name" placeholder="Setup fee">
            </div>
            <div>
                <label for="item_amount">amount <span class="muted">— a decimal string</span></label>
                <input id="item_amount" name="item_amount" placeholder="2500.00">
            </div>
            <div>
                <label for="item_interval_type">interval_type</label>
                <select id="item_interval_type" name="item_interval_type">
                    <?php foreach (\Suqo\IntervalType::cases() as $case) { ?>
                        <option value="<?= e($case->value) ?>" <?= $case === \Suqo\IntervalType::OneTime ? 'selected' : '' ?>><?= e($case->value) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label for="item_interval_count">interval_count <span class="muted">— 0 for one_time</span></label>
                <input id="item_interval_count" name="item_interval_count" type="number" min="0" value="0">
            </div>
            <div>
                <label for="item_discount_amount">discount_amount <span class="muted">— optional</span></label>
                <input id="item_discount_amount" name="item_discount_amount" placeholder="500.00">
            </div>
        </div>
        <div class="actions"><button type="submit">Open checkout session</button></div>
    </form>
</div>

<h2>Read one back</h2>
<div class="panel">
    <form class="inline" method="get" action="/checkout">
        <div style="flex:1">
            <label for="read_id">public_id</label>
            <input id="read_id" name="id" value="<?= e($id) ?>" placeholder="cks_…">
        </div>
        <button type="submit">Read</button>
    </form>
    <p class="muted" style="margin-bottom:0">A paid or expired session answers 404 — that is the ordinary end of its life, and the error body carries the session's own <code>return_url</code>.</p>
</div>
