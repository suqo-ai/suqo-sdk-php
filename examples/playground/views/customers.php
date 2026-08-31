<?php

declare(strict_types=1);

/** @var \Suqo\Exception\SuqoError|null $error */
?>
<h1>Customers</h1>
<p class="lede">The resource exists so the shape of the client is stable; every operation refuses (§10.3).</p>

<?php require __DIR__ . '/_error.php'; ?>

<div class="panel">
    <h2 style="margin-top:0">Why</h2>
    <p class="muted" style="margin:0 0 10px">
        openapi.yaml declares <code>GET /customers/</code> and <code>GET /customers/{id}/</code>, but
        §10.3 of the specification mandates <code>NotImplementedError</code> and §14 forbids adding
        public surface the specification does not describe. Implementing them is a specification
        revision, not an SDK decision.
    </p>
    <p class="muted" style="margin:0">
        The record type is already un-stubbed, because §9.4 ties that to openapi.yaml rather than to
        §10.3. Decoding a record works today:
    </p>
    <?php
    $sample = \Suqo\Model\Customer::fromWire([
        'id' => 7,
        'buyer_phone' => '9841000100',
        'buyer_email' => 'ram@client.com',
        'full_name' => 'Ram Shrestha',
        'created_at' => '2026-07-03T10:15:00Z',
    ]);
    ?>
    <dl class="kv" style="margin-top:12px">
        <dt>id</dt><dd><?= (int) $sample->id ?></dd>
        <dt>buyerPhone</dt><dd><?= e($sample->buyerPhone) ?></dd>
        <dt>buyerEmail</dt><dd><?= e($sample->buyerEmail) ?></dd>
        <dt>fullName</dt><dd><?= e($sample->fullName) ?></dd>
        <dt>createdAt</dt><dd><?= e($sample->createdAt) ?></dd>
    </dl>
</div>
