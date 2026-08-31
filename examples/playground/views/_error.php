<?php

declare(strict_types=1);

/** @var \Suqo\Exception\SuqoError|null $error */

if (!isset($error) || $error === null) {
    return;
}
?>
<div class="panel" style="border-color: var(--err)">
    <h2 style="margin-top:0">
        <?= e((new ReflectionClass($error))->getShortName()) ?>
        <span class="pill">HTTP <?= (int) $error->status ?></span>
    </h2>
    <dl class="kv">
        <dt>message</dt><dd><?= e($error->getMessage()) ?></dd>
        <dt>request id</dt><dd><?= e($error->requestId === '' ? '—' : $error->requestId) ?></dd>
        <?php if ($error instanceof \Suqo\Exception\KycRequiredError) { ?>
            <dt>kyc status</dt><dd><?= e($error->kycStatus) ?></dd>
        <?php } ?>
        <?php if ($error->retryAfter !== null) { ?>
            <dt>retry after</dt><dd><?= e((string) $error->retryAfter) ?>s</dd>
        <?php } ?>
    </dl>
    <?php if ($error->fieldErrors !== []) { ?>
        <h2>Field errors</h2>
        <table>
            <tr><th>field</th><th>messages</th></tr>
            <?php foreach ($error->fieldErrors as $field => $messages) { ?>
                <tr><td class="num"><?= e($field) ?></td><td><?= e(implode(' · ', $messages)) ?></td></tr>
            <?php } ?>
        </table>
    <?php } ?>
    <?php if ($error->rawBody !== null) { ?>
        <h2>Raw body <span class="muted">— wire names preserved (N8)</span></h2>
        <pre><?= e(json($error->rawBody)) ?></pre>
    <?php } ?>
</div>
