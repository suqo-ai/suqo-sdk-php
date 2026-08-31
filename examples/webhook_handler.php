<?php

declare(strict_types=1);

/**
 * A minimal webhook endpoint. No client, no API key, no network access — this file
 * works unchanged in a serverless handler.
 */

require __DIR__ . '/../vendor/autoload.php';

use Suqo\Webhook;

// The exact bytes received. Never a re-serialised parse: the signature covers
// bytes, not structure.
$rawBody = (string) file_get_contents('php://input');

$verified = Webhook::verify(
    rawBody: $rawBody,
    signature: $_SERVER['HTTP_X_SUQO_SIGNATURE'] ?? null,
    timestamp: $_SERVER['HTTP_X_SUQO_TIMESTAMP'] ?? null,
    secret: (string) getenv('SUQO_WEBHOOK_SECRET'),
);

if (!$verified) {
    http_response_code(400);
    echo 'invalid signature';
    exit;
}

$event = json_decode($rawBody, true);

// Handle the event, then acknowledge quickly.
http_response_code(204);
