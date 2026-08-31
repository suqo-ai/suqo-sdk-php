<?php

declare(strict_types=1);

/**
 * Playground bootstrap: autoloading, session, and the handful of helpers the
 * front controller and views share.
 *
 * The API key lives in the PHP session and nowhere else — never in a file, never
 * in a query string, never in the page source.
 */

$autoload = __DIR__ . '/../../vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Dependencies are not installed.\n\nRun:  composer install\n";
    exit;
}

require $autoload;

use Suqo\Exception\SuqoConfigError;
use Suqo\Exception\SuqoError;
use Suqo\SuqoClient;

const SESSION_KEY = 'suqo_api_key';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'path' => '/',
]);
session_start();

/** HTML-escape. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Trimmed POST field, or null when blank. */
function post(string $name): ?string
{
    $value = $_POST[$name] ?? null;

    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);

    return $value === '' ? null : $value;
}

/** Trimmed GET field, or null when blank. */
function query(string $name): ?string
{
    $value = $_GET[$name] ?? null;

    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);

    return $value === '' ? null : $value;
}

function queryInt(string $name): ?int
{
    $value = query($name);

    return $value !== null && ctype_digit($value) ? (int) $value : null;
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrfToken()) . '">';
}

function assertCsrf(): void
{
    $supplied = $_POST['_token'] ?? null;

    if (!is_string($supplied) || !hash_equals(csrfToken(), $supplied)) {
        http_response_code(419);
        exit('Session expired. Reload the page and try again.');
    }
}

/** @param 'ok'|'error'|'info' $kind */
function flash(string $kind, string $message): void
{
    $_SESSION['flash'][] = ['kind' => $kind, 'message' => $message];
}

/** @return list<array{kind: string, message: string}> */
function takeFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($flashes) ? $flashes : [];
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function apiKey(): ?string
{
    $key = $_SESSION[SESSION_KEY] ?? null;

    return is_string($key) && $key !== '' ? $key : null;
}

function isConnected(): bool
{
    return apiKey() !== null;
}

/** Show enough of the key to recognise it, never enough to use it. */
function maskedKey(): string
{
    $key = apiKey() ?? '';
    $tail = substr($key, -4);
    $prefix = str_starts_with($key, 'su_test_key_') ? 'su_test_key_' : 'su_key_';

    return $prefix . '••••' . $tail;
}

/**
 * Build a client from the session key. Options come from the connect form, so
 * nothing in this project has to be edited to point it somewhere else.
 */
function client(): SuqoClient
{
    $key = apiKey();

    if ($key === null) {
        redirect('/');
    }

    $timeout = $_SESSION['suqo_timeout'] ?? null;
    $retries = $_SESSION['suqo_retries'] ?? null;

    return new SuqoClient(
        apiKey: $key,
        timeout: is_float($timeout) ? $timeout : null,
        maxRetries: is_int($retries) ? $retries : null,
        logLevel: 'off',
    );
}

/**
 * Run an SDK call and render its failure rather than blowing up the page.
 *
 * @template T
 *
 * @param  callable(SuqoClient): T $call
 * @return array{0: T|null, 1: SuqoError|null}
 */
function attempt(callable $call): array
{
    try {
        return [$call(client()), null];
    } catch (SuqoConfigError $e) {
        flash('error', 'Configuration: ' . $e->getMessage());

        return [null, null];
    } catch (SuqoError $e) {
        return [null, $e];
    }
}

/** Pretty-print any decoded wire value for display. */
function json(mixed $data): string
{
    if ($data === null) {
        return '';
    }

    return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/** One-line description of an SDK error, for a flash message. */
function describeError(SuqoError $error): string
{
    return sprintf(
        '%s (HTTP %d, request %s): %s',
        (new \ReflectionClass($error))->getShortName(),
        $error->status,
        $error->requestId === '' ? '-' : $error->requestId,
        $error->getMessage(),
    );
}

/**
 * @param array<string, mixed> $data
 */
function view(string $name, array $data = []): never
{
    $data['flashes'] = takeFlashes();

    $__view = __DIR__ . '/views/' . $name . '.php';
    $__layout = __DIR__ . '/views/layout.php';

    extract($data, EXTR_SKIP);

    ob_start();
    require $__view;
    $content = (string) ob_get_clean();

    require $__layout;
    exit;
}
