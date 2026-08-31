<?php

declare(strict_types=1);

/** @var string $content */
/** @var list<array{kind: string, message: string}> $flashes */

$nav = [
    '/' => 'Overview',
    '/products' => 'Products',
    '/subscriptions' => 'Subscriptions',
    '/subscriptions/new' => 'Create',
    '/customers' => 'Customers',
    '/webhook' => 'Webhooks',
];

$current = rtrim(parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/', '/');
$current = $current === '' ? '/' : $current;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SUQO PHP SDK playground</title>
<style>
:root {
    --bg: #f7f7f5; --panel: #fff; --ink: #16161a; --muted: #6b6b73;
    --line: #e3e3df; --accent: #1f6feb; --ok: #157347; --err: #b42318;
    --code-bg: #f2f2ef;
}
@media (prefers-color-scheme: dark) {
    :root {
        --bg: #141416; --panel: #1c1c20; --ink: #ececf0; --muted: #9a9aa4;
        --line: #2c2c32; --accent: #6ea8ff; --ok: #4ec27f; --err: #ff8a7a;
        --code-bg: #101014;
    }
}
* { box-sizing: border-box; }
body {
    margin: 0; background: var(--bg); color: var(--ink);
    font: 14px/1.55 ui-sans-serif, -apple-system, "Segoe UI", Roboto, sans-serif;
}
a { color: var(--accent); }
header {
    border-bottom: 1px solid var(--line); background: var(--panel);
    padding: 14px 20px; display: flex; gap: 18px; align-items: center; flex-wrap: wrap;
}
header .brand { font-weight: 650; letter-spacing: -0.01em; }
nav { display: flex; gap: 14px; flex-wrap: wrap; }
nav a { text-decoration: none; color: var(--muted); padding: 3px 0; }
nav a.on { color: var(--ink); box-shadow: inset 0 -2px 0 var(--accent); }
header .spacer { flex: 1; }
.session { display: flex; gap: 10px; align-items: center; color: var(--muted); font-size: 13px; }
.env { font: 600 11px/1 ui-monospace, monospace; padding: 4px 7px; border-radius: 4px; border: 1px solid var(--line); }
.env.sandbox { color: var(--ok); }
.env.live { color: var(--err); }
main { max-width: 1040px; margin: 0 auto; padding: 24px 20px 60px; }
h1 { font-size: 20px; margin: 0 0 6px; letter-spacing: -0.01em; }
h2 { font-size: 15px; margin: 26px 0 10px; }
p.lede { color: var(--muted); margin: 0 0 22px; }
.panel { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 18px; margin-bottom: 18px; }
.flash { border-radius: 6px; padding: 10px 13px; margin-bottom: 14px; border: 1px solid var(--line); background: var(--panel); }
.flash.ok { border-color: var(--ok); }
.flash.error { border-color: var(--err); }
label { display: block; font-size: 12px; color: var(--muted); margin: 0 0 4px; }
input, textarea, select, button {
    font: inherit; color: inherit; background: var(--bg);
    border: 1px solid var(--line); border-radius: 6px; padding: 8px 10px; width: 100%;
}
textarea { font-family: ui-monospace, monospace; font-size: 12.5px; min-height: 120px; }
button { background: var(--accent); color: #fff; border-color: transparent; cursor: pointer; font-weight: 550; }
button.ghost { background: transparent; color: var(--muted); border-color: var(--line); font-weight: 450; }
.row { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 12px; }
.actions { display: flex; gap: 10px; margin-top: 4px; }
.actions button { width: auto; padding: 8px 16px; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
th { color: var(--muted); font-weight: 550; font-size: 12px; }
td.num { font-family: ui-monospace, monospace; }
code, pre { font-family: ui-monospace, monospace; }
pre { background: var(--code-bg); border: 1px solid var(--line); border-radius: 6px; padding: 12px; overflow-x: auto; font-size: 12.5px; margin: 0; }
.scroll { overflow-x: auto; }
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; }
.stat { border: 1px solid var(--line); border-radius: 6px; padding: 11px 13px; }
.stat b { display: block; font-size: 19px; font-weight: 600; }
.stat span { color: var(--muted); font-size: 12px; }
.muted { color: var(--muted); }
.pill { font: 600 11px/1 ui-monospace, monospace; border: 1px solid var(--line); border-radius: 4px; padding: 3px 6px; }
dl.kv { display: grid; grid-template-columns: max-content 1fr; gap: 6px 18px; margin: 0; }
dl.kv dt { color: var(--muted); }
dl.kv dd { margin: 0; font-family: ui-monospace, monospace; font-size: 12.5px; word-break: break-all; }
.pager { display: flex; gap: 10px; align-items: center; margin-top: 14px; }
.pager a { text-decoration: none; border: 1px solid var(--line); border-radius: 6px; padding: 6px 12px; }
form.inline { display: flex; gap: 8px; align-items: flex-end; }
form.inline input { width: auto; }
form.inline button { width: auto; padding: 8px 14px; }
</style>
</head>
<body>
<header>
    <span class="brand">SUQO PHP SDK</span>
    <?php if (isConnected()) { ?>
        <nav>
            <?php foreach ($nav as $href => $title) { ?>
                <a href="<?= e($href) ?>" class="<?= $current === rtrim($href, '/') || ($href === '/' && $current === '/') ? 'on' : '' ?>"><?= e($title) ?></a>
            <?php } ?>
        </nav>
    <?php } ?>
    <span class="spacer"></span>
    <?php if (isConnected()) {
        $env = client()->config->environment->value; ?>
        <span class="session">
            <span class="env <?= e($env) ?>"><?= e($env) ?></span>
            <code><?= e(maskedKey()) ?></code>
            <form method="post" action="/disconnect" style="width:auto">
                <?= csrfField() ?>
                <button class="ghost" type="submit">Disconnect</button>
            </form>
        </span>
    <?php } ?>
</header>
<main>
    <?php foreach ($flashes as $flash) { ?>
        <div class="flash <?= e($flash['kind']) ?>"><?= e($flash['message']) ?></div>
    <?php } ?>
    <?= $content ?>
</main>
</body>
</html>
