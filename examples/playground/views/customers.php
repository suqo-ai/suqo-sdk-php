<?php

declare(strict_types=1);

/** @var \Suqo\Model\Page<\Suqo\Model\Customer>|null $page */
/** @var \Suqo\Exception\SuqoError|null $error */
?>
<h1>Customers</h1>
<p class="lede">A customer is also created implicitly the first time someone subscribes; <code>create()</code> records one without opening a subscription.</p>

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

<h2>Record a customer <span class="muted">— an upsert</span></h2>
<div class="panel">
    <form method="post" action="/customers">
        <?= csrfField() ?>
        <div class="row">
            <div>
                <label for="c_email">email <span class="muted">— required, identifies the buyer</span></label>
                <input id="c_email" name="email" type="email" required placeholder="ram@example.com">
            </div>
            <div>
                <label for="c_phone">phone <span class="muted">— 96/97/98 + 8 digits</span></label>
                <input id="c_phone" name="phone" placeholder="9810000001">
            </div>
            <div>
                <label for="c_name">full_name</label>
                <input id="c_name" name="full_name" placeholder="Ram Bahadur">
            </div>
            <div>
                <label for="c_address">address</label>
                <input id="c_address" name="address" placeholder="Kathmandu, Nepal">
            </div>
        </div>
        <div class="actions"><button type="submit">Record customer</button></div>
    </form>
    <p class="muted" style="margin-bottom:0">An email or phone this account already holds is <em>corrected</em> and answered 200 rather than 201 — which is what makes the call safe to retry after a timeout. A <code>+977</code> prefix, a leading zero, spaces and dashes are accepted and stripped.</p>
</div>

<h2>Correct one</h2>
<div class="panel">
    <form method="post" action="/customers/update">
        <?= csrfField() ?>
        <div class="row">
            <div>
                <label for="u_id">customer_id</label>
                <input id="u_id" name="customer_id" required placeholder="cus_…">
            </div>
            <div>
                <label for="u_name">full_name</label>
                <input id="u_name" name="full_name">
                <label style="margin-top:6px"><input type="checkbox" name="clear[]" value="full_name" style="width:auto"> send <code>""</code> to clear</label>
            </div>
            <div>
                <label for="u_email">email</label>
                <input id="u_email" name="email" type="email">
            </div>
            <div>
                <label for="u_address">address</label>
                <input id="u_address" name="address">
                <label style="margin-top:6px"><input type="checkbox" name="clear[]" value="address" style="width:auto"> send <code>""</code> to clear</label>
            </div>
        </div>
        <div class="actions"><button type="submit">Update customer</button></div>
    </form>
    <p class="muted" style="margin-bottom:0">Only the fields you fill are sent. <code>null</code> leaves a field alone; <code>""</code> clears it — the checkboxes are that difference. The phone identifies the buyer and cannot be changed.</p>
</div>
