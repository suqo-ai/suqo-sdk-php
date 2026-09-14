<?php

declare(strict_types=1);

/** @var \Suqo\Model\Page<\Suqo\Model\Customer>|null $page */
/** @var \Suqo\Exception\SuqoError|null $error */
?>
<h1>Customers</h1>
<p class="lede">Read-only. A customer is created implicitly the first time someone subscribes.</p>

<?php require __DIR__ . '/_error.php'; ?>

<?php if ($page !== null) { ?>
    <p class="muted"><?= (int) $page->count ?> total.
        <code>id</code> is a public id (<code>cus_…</code>) — not an integer, not a UUID.</p>

    <table>
        <thead>
            <tr>
                <th>id</th><th>phone</th><th>email</th><th>name</th><th>address</th><th>created</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($page->results as $customer) { ?>
                <tr>
                    <td><code><?= e($customer->id) ?></code></td>
                    <td><?= e($customer->buyerPhone) ?></td>
                    <td><?= e($customer->buyerEmail ?? '—') ?></td>
                    <td><?= e($customer->fullName ?? '—') ?></td>
                    <td><?= e($customer->address ?? '—') ?></td>
                    <td class="muted"><?= e($customer->createdAt) ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <?php if ($page->results === []) { ?>
        <p class="muted">No customers on this account yet.</p>
    <?php } ?>

    <div class="panel">
        <h2 style="margin-top:0">Nullability</h2>
        <p class="muted" style="margin:0">
            Every field except <code>id</code>, <code>buyerPhone</code> and <code>createdAt</code>
            can be null — the em dashes above are real nulls, not empty strings. Timestamps carry
            microseconds and a <code>+05:45</code> offset rather than <code>Z</code>, and are kept
            as opaque strings.
        </p>
    </div>
<?php } ?>
