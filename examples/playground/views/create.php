<?php

declare(strict_types=1);

/** @var \Suqo\Model\CreateSubscriptionResponse|null $result */
/** @var \Suqo\Exception\SuqoError|null $error */
/** @var array<string, mixed>|null $sent */
?>
<h1>Create a subscription</h1>
<p class="lede">The form field is <code>customer</code>; the body that goes out says <code>client</code>. Watch it happen below.</p>

<?php require __DIR__ . '/_error.php'; ?>

<?php if ($result !== null) { ?>
    <div class="panel" style="border-color: var(--ok)">
        <h2 style="margin-top:0">Created</h2>
        <dl class="kv">
            <dt>pbpId</dt><dd><?= e($result->pbpId) ?></dd>
            <dt>returnUrl</dt><dd><?= e($result->returnUrl) ?></dd>
            <dt>customer</dt><dd><?= e($result->customer?->fullName) ?> &lt;<?= e($result->customer?->email) ?>&gt;</dd>
        </dl>
        <h2>Response, raw</h2>
        <pre><?= e(json($result->toArray())) ?></pre>
    </div>
<?php } ?>

<?php if ($sent !== null) { ?>
    <div class="panel">
        <h2 style="margin-top:0">Request body that was sent <span class="muted">— §3 write rename</span></h2>
        <pre><?= e(json($sent)) ?></pre>
    </div>
<?php } ?>

<div class="panel">
    <form method="post" action="/subscriptions">
        <?= csrfField() ?>

        <h2 style="margin-top:0">Plan billing point</h2>
        <div class="row">
            <div>
                <label for="pbp_id">pbp_id <span class="muted">required</span></label>
                <input id="pbp_id" name="pbp_id" required placeholder="pbp_3n9k2x" value="<?= e(post('pbp_id')) ?>">
            </div>
            <div>
                <label for="return_url">return_url</label>
                <input id="return_url" name="return_url" type="url" placeholder="https://merchant.example.com/thanks" value="<?= e(post('return_url')) ?>">
            </div>
        </div>

        <h2>Customer <span class="muted">— serialised as <code>client</code></span></h2>
        <div class="row">
            <div>
                <label for="phone">phone <span class="muted">required</span></label>
                <input id="phone" name="phone" required placeholder="9841000100" value="<?= e(post('phone')) ?>">
            </div>
            <div>
                <label for="full_name">full_name <span class="muted">required</span></label>
                <input id="full_name" name="full_name" required placeholder="Ram Shrestha" value="<?= e(post('full_name')) ?>">
            </div>
            <div>
                <label for="email">email <span class="muted">required</span></label>
                <input id="email" name="email" type="email" required placeholder="ram@client.com" value="<?= e(post('email')) ?>">
            </div>
            <div>
                <label for="address">address</label>
                <input id="address" name="address" placeholder="Kathmandu, Nepal" value="<?= e(post('address')) ?>">
            </div>
        </div>

        <h2>Billing <span class="muted">— optional; keys keep their <code>billing_</code> prefix</span></h2>
        <div class="row">
            <div>
                <label for="billing_business_name">billing_business_name</label>
                <input id="billing_business_name" name="billing_business_name" placeholder="ABC Pvt Ltd." value="<?= e(post('billing_business_name')) ?>">
            </div>
            <div>
                <label for="billing_email">billing_email</label>
                <input id="billing_email" name="billing_email" type="email" value="<?= e(post('billing_email')) ?>">
            </div>
            <div>
                <label for="billing_address">billing_address</label>
                <input id="billing_address" name="billing_address" value="<?= e(post('billing_address')) ?>">
            </div>
            <div>
                <label for="billing_pan_vat">billing_pan_vat</label>
                <input id="billing_pan_vat" name="billing_pan_vat" value="<?= e(post('billing_pan_vat')) ?>">
            </div>
        </div>

        <h2>Shipping <span class="muted">— optional</span></h2>
        <div class="row">
            <div>
                <label for="shipping_full_name">full_name</label>
                <input id="shipping_full_name" name="shipping_full_name" value="<?= e(post('shipping_full_name')) ?>">
            </div>
            <div>
                <label for="shipping_phone">phone</label>
                <input id="shipping_phone" name="shipping_phone" value="<?= e(post('shipping_phone')) ?>">
            </div>
            <div>
                <label for="shipping_email">email</label>
                <input id="shipping_email" name="shipping_email" type="email" value="<?= e(post('shipping_email')) ?>">
            </div>
            <div>
                <label for="shipping_address">address</label>
                <input id="shipping_address" name="shipping_address" value="<?= e(post('shipping_address')) ?>">
            </div>
        </div>

        <div class="actions"><button type="submit">Create subscription</button></div>
    </form>
</div>

<p class="muted">Writes are never retried (<code>WRITES_RETRYABLE = false</code>), so a failed create is exactly one attempt.</p>
