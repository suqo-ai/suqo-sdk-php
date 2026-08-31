<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * §5.1 — the mechanically checkable invariants. I1 and I4 are also enforced by
 * tools/check-invariants.sh in CI; keeping them here means a plain `composer test`
 * catches a regression too.
 */
final class InvariantsTest extends TestCase
{
    private const SRC = __DIR__ . '/../../src';

    public function testI1ThereIsExactlyOneHttpCallSite(): void
    {
        $callers = $this->filesMatching('/->send\s*\(/');

        self::assertSame(
            ['Http/Transport.php'],
            $callers,
            'Only the transport may invoke the HTTP client.',
        );
    }

    public function testOnlyTheDefaultClientTouchesCurl(): void
    {
        self::assertSame(
            ['Http/CurlHttpClient.php'],
            $this->filesMatching('/\bcurl_(init|exec|setopt|setopt_array|getinfo|errno|error)\s*\(/'),
        );
    }

    public function testI4EveryUrlPathLiteralLivesInTheEndpointTable(): void
    {
        self::assertSame(
            ['Endpoints.php'],
            $this->filesMatching('#[\'"]/api/#'),
            'No URL path literal may appear outside the endpoint table.',
        );
    }

    public function testI3ResourcesDoNotMapStatusCodes(): void
    {
        foreach ($this->files() as $relative => $contents) {
            if (!str_starts_with($relative, 'Resource/')) {
                continue;
            }

            self::assertStringNotContainsString('ErrorMapper', $contents, $relative);
            self::assertDoesNotMatchRegularExpression('/\bstatus\s*(===|>=|==)/', $contents, $relative);
        }
    }

    public function testI6TheWriteRetryDecisionIsASingleConstant(): void
    {
        self::assertSame(
            ['Constants.php'],
            $this->filesMatching('/WRITES_RETRYABLE/'),
            'The constant is declared, and flipped, in exactly one place.',
        );

        self::assertSame(
            ['Constants.php', 'Http/RetryPolicy.php'],
            $this->filesMatching('/writesRetryable\(/'),
            'Only the retry policy reads it.',
        );
    }

    public function testI5NoDecimalWireFieldIsCastToFloat(): void
    {
        foreach ($this->files() as $relative => $contents) {
            if (!str_starts_with($relative, 'Model/') && !str_starts_with($relative, 'Params/')) {
                continue;
            }

            self::assertDoesNotMatchRegularExpression('/\(float\)|\(double\)|floatval/', $contents, $relative);
        }
    }

    /**
     * @return list<string> Relative paths, sorted.
     */
    private function filesMatching(string $pattern): array
    {
        $hits = [];

        foreach ($this->files() as $relative => $contents) {
            if (preg_match($pattern, $contents) === 1) {
                $hits[] = $relative;
            }
        }

        sort($hits);

        return $hits;
    }

    /**
     * @return array<string, string> Relative path => contents.
     */
    private function files(): array
    {
        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::SRC, \FilesystemIterator::SKIP_DOTS),
        );

        $out = [];
        $root = realpath(self::SRC);
        self::assertIsString($root);

        /** @var \SplFileInfo $file */
        foreach ($directory as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = (string) $file->getRealPath();
            $out[ltrim(substr($path, strlen($root)), '/')] = (string) file_get_contents($path);
        }

        ksort($out);

        return $out;
    }
}
