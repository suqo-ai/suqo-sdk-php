<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Suqo\Exception\AuthenticationError;
use Suqo\Exception\CancelledError;
use Suqo\Exception\ErrorMapper;
use Suqo\Exception\KycRequiredError;
use Suqo\Exception\NetworkError;
use Suqo\Exception\NotFoundError;
use Suqo\Exception\NotImplementedError;
use Suqo\Exception\RateLimitError;
use Suqo\Exception\ServerError;
use Suqo\Exception\SuqoError;
use Suqo\Exception\ValidationError;

/**
 * §13 — Errors, E1..E12.
 */
final class ErrorsTest extends TestCase
{
    public function testE1DetailBodySuppliesTheMessage(): void
    {
        $error = ErrorMapper::map(401, ['detail' => 'bad token'], 'req_1');

        self::assertInstanceOf(AuthenticationError::class, $error);
        self::assertSame('bad token', $error->getMessage());
        self::assertSame(401, $error->status);
        self::assertSame('req_1', $error->requestId);
    }

    public function testE2EmptyBodyFallsBackToTheDefaultMessage(): void
    {
        $error = ErrorMapper::map(401, null, 'req_1');

        self::assertInstanceOf(AuthenticationError::class, $error);
        self::assertSame('Authentication failed.', $error->getMessage());
    }

    public function testE3KycShapedBodyOn403(): void
    {
        $error = ErrorMapper::map(
            403,
            ['status_code' => 'PENDING', 'message' => 'KYC required'],
            'req_1',
        );

        self::assertInstanceOf(KycRequiredError::class, $error);
        self::assertSame('PENDING', $error->kycStatus);
        self::assertSame('KYC required', $error->getMessage());
    }

    public function testE4NonKycBodyOn403IsABareSuqoError(): void
    {
        $error = ErrorMapper::map(403, ['detail' => 'forbidden'], 'req_1');

        self::assertSame(SuqoError::class, $error::class);
        self::assertSame('Unexpected status 403.', $error->getMessage());
    }

    public function testE5FieldErrorsMapEveryKeyToAList(): void
    {
        $error = ErrorMapper::map(
            400,
            ['email' => ['required'], 'phone' => 'invalid'],
            'req_1',
        );

        self::assertInstanceOf(ValidationError::class, $error);
        self::assertSame(
            ['email' => ['required'], 'phone' => ['invalid']],
            $error->fieldErrors,
        );
        self::assertSame('required', $error->getMessage());
    }

    public function testE6EmptyBodyOn400YieldsAnEmptyFieldErrorMap(): void
    {
        $error = ErrorMapper::map(400, [], 'req_1');

        self::assertInstanceOf(ValidationError::class, $error);
        self::assertSame('Validation failed.', $error->getMessage());
        self::assertSame([], $error->fieldErrors);
    }

    public function testAbsentBodyOn400AlsoYieldsAnEmptyFieldErrorMap(): void
    {
        $error = ErrorMapper::map(400, null, 'req_1');

        self::assertInstanceOf(ValidationError::class, $error);
        self::assertSame([], $error->fieldErrors);
    }

    public function testE7NotFound(): void
    {
        self::assertInstanceOf(NotFoundError::class, ErrorMapper::map(404, null, 'req_1'));
        self::assertSame('Not found.', ErrorMapper::map(404, null, 'req_1')->getMessage());
    }

    public function testE8RateLimited(): void
    {
        $error = ErrorMapper::map(429, null, 'req_1');

        self::assertInstanceOf(RateLimitError::class, $error);
        self::assertSame('Rate limited.', $error->getMessage());
    }

    public function testE9ServerErrorNamesTheStatus(): void
    {
        $error = ErrorMapper::map(503, null, 'req_1');

        self::assertInstanceOf(ServerError::class, $error);
        self::assertSame('Server error (503).', $error->getMessage());
    }

    public function testE10UnmappedStatusIsABareSuqoError(): void
    {
        $error = ErrorMapper::map(418, null, 'req_1');

        self::assertSame(SuqoError::class, $error::class);
        self::assertSame('Unexpected status 418.', $error->getMessage());
    }

    public function testE11RawBodyPreservesWireNames(): void
    {
        $body = ['client' => ['email' => 'a@example.com'], 'detail' => 'nope'];
        $error = ErrorMapper::map(400, $body, 'req_1');

        self::assertIsArray($error->rawBody);
        self::assertArrayHasKey('client', $error->rawBody);
        self::assertArrayNotHasKey('customer', $error->rawBody);
    }

    /**
     * @return list<array{class-string<SuqoError>}>
     */
    public static function errorTypes(): array
    {
        return [
            [AuthenticationError::class],
            [KycRequiredError::class],
            [ValidationError::class],
            [NotFoundError::class],
            [RateLimitError::class],
            [ServerError::class],
            [NetworkError::class],
            [CancelledError::class],
            [NotImplementedError::class],
        ];
    }

    /**
     * @param class-string<SuqoError> $type
     */
    #[DataProvider('errorTypes')]
    public function testE12EveryErrorTypeDerivesFromSuqoError(string $type): void
    {
        self::assertTrue(is_subclass_of($type, SuqoError::class), $type . ' must derive from SuqoError.');
    }

    public function testEveryErrorTypeInTheExceptionNamespaceIsAccountedFor(): void
    {
        $files = glob(__DIR__ . '/../../src/Exception/*.php');
        self::assertNotFalse($files);

        $classes = array_map(
            static fn (string $file): string => 'Suqo\\Exception\\' . basename($file, '.php'),
            $files,
        );

        // Everything but the base type, the mapper and the construction-time
        // config error must derive from SuqoError (N6, E12).
        $exempt = [SuqoError::class, ErrorMapper::class, \Suqo\Exception\SuqoConfigError::class];

        foreach ($classes as $class) {
            if (in_array($class, $exempt, true)) {
                continue;
            }

            self::assertTrue(
                is_subclass_of($class, SuqoError::class),
                $class . ' must derive from SuqoError.',
            );
            self::assertStringEndsWith('Error', $class, 'N6: error types carry an Error suffix.');
        }
    }

    public function testFieldErrorsAreAlwaysPresentEvenWhenNotApplicable(): void
    {
        foreach ([401, 403, 404, 429, 500, 418] as $status) {
            self::assertSame([], ErrorMapper::map($status, null, 'req_1')->fieldErrors);
        }
    }

    public function testDetailClassificationRequiresExactlyOneKey(): void
    {
        // Two keys, so not DETAIL: falls through to FIELD, whose first entry wins.
        $error = ErrorMapper::map(404, ['detail' => 'nope', 'code' => 'gone'], 'req_1');

        self::assertSame('nope', $error->getMessage());
        self::assertInstanceOf(NotFoundError::class, $error);
    }

    public function testKycClassificationRequiresBothKeysToBeStrings(): void
    {
        $error = ErrorMapper::map(403, ['status_code' => 403, 'message' => 'nope'], 'req_1');

        self::assertSame(SuqoError::class, $error::class);
        self::assertSame('Unexpected status 403.', $error->getMessage());
    }

    public function testNonObjectBodyClassifiesAsNone(): void
    {
        self::assertSame('Not found.', ErrorMapper::map(404, 'plain text', 'req_1')->getMessage());
        self::assertSame('Not found.', ErrorMapper::map(404, ['a', 'b'], 'req_1')->getMessage());
    }

    public function testFieldErrorMessageFollowsJsonDocumentOrder(): void
    {
        // B12: PHP's json_decode preserves document order.
        $body = json_decode('{"phone": "invalid", "email": ["required"]}', true);
        $error = ErrorMapper::map(400, $body, 'req_1');

        self::assertSame('invalid', $error->getMessage());
    }
}
