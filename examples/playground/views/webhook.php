<?php

declare(strict_types=1);

/** @var bool|null $verified */
/** @var array<string, string|null> $input */

$body = $input['raw_body'] ?? '{"event":"subscription.activated","subscription_id":"3fa85f64-5717-4562-b3fc-2c963f66afa6"}';
?>
<h1>Webhook verification</h1>
<p class="lede">No client, no API key, no network access — callable from a serverless handler. Never throws; every failure path returns <code>false</code>.</p>

<?php if ($verified !== null) { ?>
    <div class="panel" style="border-color: var(--<?= $verified ? 'ok' : 'err' ?>)">
        <h2 style="margin-top:0"><?= $verified ? 'Verified' : 'Rejected' ?></h2>
        <p class="muted" style="margin:0">
            <?= $verified
                ? 'Signature matched in constant time and the timestamp is inside the freshness window.'
                : 'One of: missing header, bad prefix, wrong length, non-numeric timestamp, stale or future timestamp, or a signature that does not match these exact bytes.' ?>
        </p>
    </div>
<?php } ?>

<div class="panel">
    <form method="post" action="/webhook">
        <?= csrfField() ?>
        <div>
            <label for="raw_body">Raw body <span class="muted">— exact bytes; a re-serialised parse will not verify</span></label>
            <textarea id="raw_body" name="raw_body" spellcheck="false"><?= e($body) ?></textarea>
        </div>
        <div class="row" style="margin-top:12px">
            <div>
                <label for="secret">Signing secret</label>
                <input id="secret" name="secret" autocomplete="off" value="<?= e($input['secret'] ?? 'whsec_demo_secret') ?>">
            </div>
            <div>
                <label for="timestamp">Timestamp header</label>
                <input id="timestamp" name="timestamp" value="<?= e($input['timestamp'] ?? '') ?>" placeholder="<?= e((string) time()) ?>">
            </div>
            <div>
                <label for="max_age">max_age (seconds)</label>
                <input id="max_age" name="max_age" type="number" min="1" value="<?= e($input['max_age'] ?? '300') ?>">
            </div>
        </div>
        <div style="margin-bottom:12px">
            <label for="signature">Signature header</label>
            <input id="signature" name="signature" spellcheck="false" value="<?= e($input['signature'] ?? '') ?>" placeholder="sha256=…">
        </div>
        <div class="actions">
            <button type="submit" name="action" value="verify">Verify</button>
            <button class="ghost" type="submit" name="action" value="sign">Sign it for me</button>
        </div>
    </form>
</div>

<div class="panel">
    <h2 style="margin-top:0">The rules (§12.2)</h2>
    <table>
        <tr><th>Check</th><th>Behaviour</th></tr>
        <tr><td>Missing signature or timestamp</td><td>false</td></tr>
        <tr><td>Non-numeric timestamp</td><td>false</td></tr>
        <tr><td>Older than <code>max_age</code> (default 300s)</td><td>false</td></tr>
        <tr><td>More than 60s in the future</td><td>false — not configurable</td></tr>
        <tr><td>Signature without <code>sha256=</code></td><td>false</td></tr>
        <tr><td>Hex payload not 64 characters</td><td>false</td></tr>
        <tr><td>Signed payload</td><td><code>timestamp + "." + raw_body</code></td></tr>
        <tr><td>Comparison</td><td>constant time (<code>hash_equals</code>)</td></tr>
    </table>
</div>

<div class="panel">
    <h2 style="margin-top:0">In a real handler</h2>
    <pre>$verified = Suqo\Webhook::verify(
    rawBody: file_get_contents('php://input'),
    signature: $_SERVER['HTTP_X_SUQO_SIGNATURE'] ?? null,
    timestamp: $_SERVER['HTTP_X_SUQO_TIMESTAMP'] ?? null,
    secret: getenv('SUQO_WEBHOOK_SECRET'),
);</pre>
    <p class="muted" style="margin:10px 0 0">Header names are a guess until the webhook contract is documented — the function only cares about the values.</p>
</div>
