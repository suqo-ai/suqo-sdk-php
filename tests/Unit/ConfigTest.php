<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Suqo\Config;
use Suqo\Constants;
use Suqo\Environment;
use Suqo\Exception\SuqoConfigError;
use Suqo\LogLevel;

/**
 * §13 — Configuration, C1..C8.
 */
final class ConfigTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach ([Constants::ENV_API_KEY, Constants::ENV_LOG] as $name) {
            $this->saved[$name] = getenv($name);
            $this->clearEnv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            if (is_string($value)) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            } else {
                $this->clearEnv($name);
            }
        }
    }

    public function testC1SandboxKeyResolvesSandbox(): void
    {
        $config = Config::resolve(apiKey: 'su_test_key_abc');

        self::assertSame(Environment::Sandbox, $config->environment);
        self::assertSame(Constants::SANDBOX_URL, $config->baseUrl);
    }

    public function testC2LiveKeyResolvesLive(): void
    {
        $config = Config::resolve(apiKey: 'su_key_abc');

        self::assertSame(Environment::Live, $config->environment);
        self::assertSame(Constants::LIVE_URL, $config->baseUrl);
    }

    public function testC3ForeignPrefixIsMalformed(): void
    {
        $this->expectException(SuqoConfigError::class);
        $this->expectExceptionMessage(Constants::MSG_MALFORMED_KEY);

        Config::resolve(apiKey: 'sk_live_abc');
    }

    public function testC4NoKeyAndNoEnvVarIsMalformed(): void
    {
        $this->expectException(SuqoConfigError::class);
        $this->expectExceptionMessage(Constants::MSG_MALFORMED_KEY);

        Config::resolve();
    }

    public function testC5EmptyStringKeyIsMalformed(): void
    {
        $this->expectException(SuqoConfigError::class);
        $this->expectExceptionMessage(Constants::MSG_MALFORMED_KEY);

        Config::resolve(apiKey: '');
    }

    public function testC6EnvironmentConflictNamesBothSides(): void
    {
        try {
            Config::resolve(apiKey: 'su_test_key_abc', environment: Environment::Live);
            self::fail('Expected SuqoConfigError.');
        } catch (SuqoConfigError $e) {
            self::assertSame(
                'Environment conflict: key prefix implies sandbox but environment was set to live.',
                $e->getMessage(),
            );
        }
    }

    public function testC7AgreeingEnvironmentSucceeds(): void
    {
        $config = Config::resolve(apiKey: 'su_key_abc', environment: Environment::Live);

        self::assertSame(Environment::Live, $config->environment);
    }

    public function testC8ExplicitKeyWinsOverEnvVar(): void
    {
        putenv(Constants::ENV_API_KEY . '=su_key_from_env');
        $_ENV[Constants::ENV_API_KEY] = 'su_key_from_env';

        $config = Config::resolve(apiKey: 'su_test_key_explicit');

        self::assertSame('su_test_key_explicit', $config->apiKey);
        self::assertSame(Environment::Sandbox, $config->environment);
    }

    public function testEnvVarIsUsedWhenNoKeyIsSupplied(): void
    {
        putenv(Constants::ENV_API_KEY . '=su_key_from_env');
        $_ENV[Constants::ENV_API_KEY] = 'su_key_from_env';

        self::assertSame(Environment::Live, Config::resolve()->environment);
    }

    public function testDefaultsMatchSection41(): void
    {
        $config = Config::resolve(apiKey: 'su_test_key_abc');

        self::assertSame(30.0, $config->timeout);
        self::assertSame(2, $config->maxRetries);
        self::assertSame(LogLevel::Warn, $config->logLevel);
    }

    public function testLogLevelComesFromEnvVarWhenUnset(): void
    {
        putenv(Constants::ENV_LOG . '=debug');
        $_ENV[Constants::ENV_LOG] = 'debug';

        $config = Config::resolve(apiKey: 'su_test_key_abc');

        self::assertSame(LogLevel::Debug, $config->logLevel);
    }

    public function testUnrecognisedLogEnvVarFallsBackToWarn(): void
    {
        putenv(Constants::ENV_LOG . '=chatty');
        $_ENV[Constants::ENV_LOG] = 'chatty';

        self::assertSame(LogLevel::Warn, Config::resolve(apiKey: 'su_test_key_abc')->logLevel);
    }

    private function clearEnv(string $name): void
    {
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }
}
