<?php

declare(strict_types=1);

/** @var \Suqo\Config $config */
/** @var \Suqo\Exception\SuqoError|null $error */
?>
<h1>Overview</h1>
<p class="lede">Resolved configuration, straight off the client the session key built.</p>

<?php require __DIR__ . '/_error.php'; ?>

<div class="panel">
    <dl class="kv">
        <dt>environment</dt><dd><?= e($config->environment->value) ?></dd>
        <dt>base url</dt><dd><?= e($config->baseUrl) ?></dd>
        <dt>api key</dt><dd><?= e(maskedKey()) ?></dd>
        <dt>timeout</dt><dd><?= e(rtrim(rtrim(number_format($config->timeout, 2, '.', ''), '0'), '.')) ?>s</dd>
        <dt>max retries</dt><dd><?= (int) $config->maxRetries ?> (<?= (int) $config->maxRetries + 1 ?> attempts)</dd>
        <dt>http client</dt><dd><?= e($config->httpClient::class) ?></dd>
    </dl>
    <div class="actions" style="margin-top:16px">
        <a href="/ping"><button type="button">Check connectivity</button></a>
    </div>
</div>

<div class="panel">
    <h2 style="margin-top:0">What this playground exercises</h2>
    <table>
        <tr><th>Page</th><th>SDK call</th></tr>
        <tr><td><a href="/products">Products</a></td><td class="num">products-&gt;list() / products-&gt;autoPaging()</td></tr>
        <tr><td><a href="/subscriptions">Subscriptions</a></td><td class="num">subscriptions-&gt;list() / cancel() / updateBillingCycle()</td></tr>
        <tr><td><a href="/subscriptions/new">Create</a></td><td class="num">subscriptions-&gt;create()</td></tr>
        <tr><td><a href="/customers">Customers</a></td><td class="num">customers-&gt;list() — raises NotImplementedError (§10.3)</td></tr>
        <tr><td><a href="/webhook">Webhooks</a></td><td class="num">Webhook::verify() — no client, no key</td></tr>
    </table>
</div>
