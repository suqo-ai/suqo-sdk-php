<?php

declare(strict_types=1);

/** @var list<\Suqo\Model\WebhookEndpoint> $webhooks */
/** @var \Suqo\Model\SigningSecret|null $secret */
/** @var \Suqo\Exception\SuqoError|null $error */
?>
<h1>Webhook endpoints</h1>
<p class="lede">Management — the endpoints SUQO delivers to. Verifying a delivery that has arrived is the <a href="/webhook">Webhooks</a> page, which needs no client at all.</p>

<?php require __DIR__ . '/_error.php'; ?>

<div class="panel">
    <h2 style="margin-top:0">Signing secret <span class="muted">— minted on first read</span></h2>
    <?php if ($secret !== null) { ?>
        <dl class="kv">
            <dt>signing_secret</dt><dd><?= e($secret->signingSecret) ?></dd>
        </dl>
        <p class="muted" style="margin:12px 0 0">This is what <code>Webhook::verify()</code> takes. It is not rotatable through the API — fetch it once at deploy time and keep it in configuration, never on the verification path.</p>
    <?php } else { ?>
        <p class="muted" style="margin:0">Not readable with this key.</p>
    <?php } ?>
</div>

<?php if ($webhooks !== []) { ?>
    <div class="panel scroll">
        <table>
            <tr><th>id</th><th>event</th><th>endpoint_url</th><th>active</th><th>created</th><th></th></tr>
            <?php foreach ($webhooks as $webhook) {
                $event = $webhook->event;
                $eventLabel = $event instanceof \Suqo\Model\WebhookEvent ? $event->value : (string) $event;
                $active = $webhook->isActive ?? false; ?>
                <tr>
                    <td class="num"><?= e($webhook->id) ?></td>
                    <td>
                        <span class="pill"><?= e($eventLabel) ?></span>
                        <?php if (!$event instanceof \Suqo\Model\WebhookEvent && $event !== null) { ?>
                            <div class="muted" style="font-size:11px">unrecognised — preserved (§9.3)</div>
                        <?php } ?>
                    </td>
                    <td><?= e($webhook->endpointUrl) ?></td>
                    <td><?= $active ? 'yes' : 'no' ?></td>
                    <td class="muted"><?= e($webhook->createdAt) ?></td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <form method="post" action="/endpoints/test">
                                <?= csrfField() ?>
                                <input type="hidden" name="webhook_id" value="<?= e($webhook->id) ?>">
                                <button class="ghost" type="submit">Test</button>
                            </form>
                            <form method="post" action="/endpoints/toggle">
                                <?= csrfField() ?>
                                <input type="hidden" name="webhook_id" value="<?= e($webhook->id) ?>">
                                <input type="hidden" name="is_active" value="<?= $active ? '0' : '1' ?>">
                                <button class="ghost" type="submit"><?= $active ? 'Pause' : 'Resume' ?></button>
                            </form>
                            <form method="post" action="/endpoints/delete" onsubmit="return confirm('Delete this webhook?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="webhook_id" value="<?= e($webhook->id) ?>">
                                <button class="ghost" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>

    <h2>First record, raw</h2>
    <pre><?= e(json($webhooks[0]->toArray())) ?></pre>
<?php } elseif ($error === null) { ?>
    <div class="panel muted">No webhooks registered on this account. This is the one collection on the API that answers with a bare JSON array rather than a paginated envelope.</div>
<?php } ?>

<h2>Register one</h2>
<div class="panel">
    <form method="post" action="/endpoints">
        <?= csrfField() ?>
        <div class="row">
            <div>
                <label for="event">event <span class="muted">— one webhook per event</span></label>
                <select id="event" name="event">
                    <?php foreach (\Suqo\Model\WebhookEvent::cases() as $case) { ?>
                        <option value="<?= e($case->value) ?>"><?= e($case->value) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label for="endpoint_url">endpoint_url <span class="muted">— https, public address only</span></label>
                <input id="endpoint_url" name="endpoint_url" required placeholder="https://merchant.example.com/hooks/suqo">
            </div>
        </div>
        <div class="actions"><button type="submit">Register</button></div>
    </form>
    <p class="muted" style="margin-bottom:0">The endpoint is checked before it is stored: https only, it must resolve, and <em>every</em> address it resolves to must be public — so <code>localhost</code> and a host with both a public and a loopback record are refused. Delivery re-runs the same check, because DNS can be re-pointed afterwards.</p>
</div>
