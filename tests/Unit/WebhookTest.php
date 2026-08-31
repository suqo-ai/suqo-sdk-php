<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Suqo\Webhook;

/**
 * §13 — Webhooks, W1..W11.
 */
final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test_secret';
    private const BODY = '{"event":"subscription.activated","id":"sub_1"}';

    public function testW1ValidSignatureAndFreshTimestamp(): void
    {
        $ts = (string) time();

        self::assertTrue(Webhook::verify(self::BODY, self::sign(self::BODY, $ts), $ts, self::SECRET));
    }

    public function testW2AlteredSignatureFails(): void
    {
        $ts = (string) time();
        $signature = self::sign(self::BODY, $ts);
        $tampered = substr($signature, 0, -1) . ($signature[strlen($signature) - 1] === 'a' ? 'b' : 'a');

        self::assertFalse(Webhook::verify(self::BODY, $tampered, $ts, self::SECRET));
    }

    public function testW3TimestampThreeHundredAndOneSecondsOldFails(): void
    {
        $ts = (string) (time() - 301);

        self::assertFalse(Webhook::verify(self::BODY, self::sign(self::BODY, $ts), $ts, self::SECRET));
    }

    public function testW4TimestampTwoHundredAndNinetyNineSecondsOldPasses(): void
    {
        $ts = (string) (time() - 299);

        self::assertTrue(Webhook::verify(self::BODY, self::sign(self::BODY, $ts), $ts, self::SECRET));
    }

    public function testW5TimestampSixtyOneSecondsAheadFails(): void
    {
        $ts = (string) (time() + 61);

        self::assertFalse(Webhook::verify(self::BODY, self::sign(self::BODY, $ts), $ts, self::SECRET));
    }

    public function testW6TimestampFiftyNineSecondsAheadPasses(): void
    {
        $ts = (string) (time() + 59);

        self::assertTrue(Webhook::verify(self::BODY, self::sign(self::BODY, $ts), $ts, self::SECRET));
    }

    public function testW7MissingSignatureOrTimestampFails(): void
    {
        $ts = (string) time();

        self::assertFalse(Webhook::verify(self::BODY, null, $ts, self::SECRET));
        self::assertFalse(Webhook::verify(self::BODY, self::sign(self::BODY, $ts), null, self::SECRET));
        self::assertFalse(Webhook::verify(self::BODY, null, null, self::SECRET));
    }

    public function testW8SignatureWithoutThePrefixFails(): void
    {
        $ts = (string) time();
        $unprefixed = hash_hmac('sha256', $ts . '.' . self::BODY, self::SECRET);

        self::assertFalse(Webhook::verify(self::BODY, $unprefixed, $ts, self::SECRET));
        self::assertFalse(Webhook::verify(self::BODY, 'sha1=' . $unprefixed, $ts, self::SECRET));
    }

    public function testW9HexPayloadThatIsNotSixtyFourCharactersFails(): void
    {
        $ts = (string) time();
        $hex = hash_hmac('sha256', $ts . '.' . self::BODY, self::SECRET);

        self::assertFalse(Webhook::verify(self::BODY, 'sha256=' . substr($hex, 0, 63), $ts, self::SECRET));
        self::assertFalse(Webhook::verify(self::BODY, 'sha256=' . $hex . 'ab', $ts, self::SECRET));
        self::assertFalse(Webhook::verify(self::BODY, 'sha256=', $ts, self::SECRET));
    }

    public function testNonHexSignatureOfTheRightLengthFails(): void
    {
        $ts = (string) time();

        self::assertFalse(Webhook::verify(self::BODY, 'sha256=' . str_repeat('z', 64), $ts, self::SECRET));
    }

    public function testW10NonNumericTimestampFails(): void
    {
        self::assertFalse(Webhook::verify(self::BODY, self::sign(self::BODY, 'yesterday'), 'yesterday', self::SECRET));
        self::assertFalse(Webhook::verify(self::BODY, self::sign(self::BODY, ''), '', self::SECRET));
    }

    /**
     * W11 documents why raw bytes are required: a round-trip through a JSON
     * decoder changes the bytes the signature covers.
     */
    public function testW11ReSerialisedBodyFails(): void
    {
        $raw = '{"event": "subscription.activated", "id": "sub_1"}';
        $ts = (string) time();
        $signature = self::sign($raw, $ts);

        $reSerialised = (string) json_encode(json_decode($raw, true));

        self::assertNotSame($raw, $reSerialised);
        self::assertTrue(Webhook::verify($raw, $signature, $ts, self::SECRET));
        self::assertFalse(Webhook::verify($reSerialised, $signature, $ts, self::SECRET));
    }

    public function testVerificationNeedsNoClientInstance(): void
    {
        $ts = (string) time();

        self::assertTrue(Webhook::verify(self::BODY, self::sign(self::BODY, $ts), $ts, self::SECRET));
        self::assertTrue(\Suqo\SuqoClient::verifyWebhook(self::BODY, self::sign(self::BODY, $ts), $ts, self::SECRET));
    }

    public function testMaxAgeIsConfigurableAndForwardSkewIsNot(): void
    {
        $old = (string) (time() - 600);
        self::assertFalse(Webhook::verify(self::BODY, self::sign(self::BODY, $old), $old, self::SECRET));
        self::assertTrue(Webhook::verify(self::BODY, self::sign(self::BODY, $old), $old, self::SECRET, 900));

        $ahead = (string) (time() + 120);
        self::assertFalse(Webhook::verify(self::BODY, self::sign(self::BODY, $ahead), $ahead, self::SECRET, 100000));
    }

    public function testWrongSecretFails(): void
    {
        $ts = (string) time();

        self::assertFalse(Webhook::verify(self::BODY, self::sign(self::BODY, $ts), $ts, 'whsec_other'));
    }

    public function testEmptyBodyIsVerifiable(): void
    {
        $ts = (string) time();

        self::assertTrue(Webhook::verify('', self::sign('', $ts), $ts, self::SECRET));
    }

    private static function sign(string $body, string $timestamp, string $secret = self::SECRET): string
    {
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }
}
