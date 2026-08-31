<?php

declare(strict_types=1);
?>
<h1>Connect</h1>
<p class="lede">Paste an API key. The environment is inferred from its prefix — nothing here needs editing to switch between sandbox and live.</p>

<div class="panel">
    <form method="post" action="/connect">
        <?= csrfField() ?>
        <div class="row">
            <div style="grid-column: 1 / -1">
                <label for="api_key">API key</label>
                <input id="api_key" name="api_key" type="password" autocomplete="off" spellcheck="false"
                       placeholder="su_test_key_… or su_key_…" required autofocus>
            </div>
        </div>
        <div class="row">
            <div>
                <label for="timeout">Timeout (seconds)</label>
                <input id="timeout" name="timeout" type="number" step="0.5" min="0.5" placeholder="30">
            </div>
            <div>
                <label for="max_retries">Max retries</label>
                <input id="max_retries" name="max_retries" type="number" min="0" max="10" placeholder="2">
            </div>
        </div>
        <div class="actions"><button type="submit">Connect</button></div>
    </form>
</div>

<div class="panel">
    <h2 style="margin-top:0">Where the key goes</h2>
    <p class="muted" style="margin:0 0 10px">
        The key is kept in the PHP session for this browser only. It is never written into this
        project's source, never put in a URL, and never rendered back to the page — the header shows
        a masked form of it.
    </p>
    <p class="muted" style="margin:0">
        One caveat worth knowing: PHP writes session data to a file on disk (usually under the system
        temp directory), so the key does touch the filesystem for as long as the session lives.
        <strong>Disconnect</strong> clears it and rotates the session id. This playground is a local
        development tool — bind it to 127.0.0.1 and prefer a sandbox key.
    </p>
</div>

<div class="panel">
    <h2 style="margin-top:0">Prefix rules (§4.2)</h2>
    <table>
        <tr><th>Prefix</th><th>Environment</th><th>Base URL</th></tr>
        <tr><td class="num">su_test_key_</td><td>sandbox</td><td class="num"><?= e(\Suqo\Constants::SANDBOX_URL) ?></td></tr>
        <tr><td class="num">su_key_</td><td>live</td><td class="num"><?= e(\Suqo\Constants::LIVE_URL) ?></td></tr>
    </table>
    <p class="muted" style="margin:12px 0 0">Anything else is rejected before a request is ever made.</p>
</div>

<p class="muted">The <a href="/webhook">webhook verifier</a> needs no key at all.</p>
