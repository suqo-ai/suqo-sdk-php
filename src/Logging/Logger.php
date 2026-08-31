<?php

declare(strict_types=1);

namespace Suqo\Logging;

use Suqo\LogLevel;

/**
 * §5 — level-filtered diagnostic output.
 *
 * Diagnostics go to STDERR so they never contaminate STDOUT in CLI programs.
 * A binding-level convenience only: the SDK exposes no logger injection point,
 * because §4.1 lists none.
 */
final class Logger
{
    /** @var resource|null */
    private $stream;

    /**
     * @param resource|null $stream Defaults to STDERR, resolved lazily so that
     *                              constructing a client never opens a handle.
     */
    public function __construct(
        private readonly LogLevel $level,
        $stream = null,
    ) {
        $this->stream = $stream;
    }

    /** @param array<string, mixed> $context */
    public function debug(string $event, array $context = []): void
    {
        $this->log(LogLevel::Debug, $event, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $event, array $context = []): void
    {
        $this->log(LogLevel::Info, $event, $context);
    }

    /** @param array<string, mixed> $context */
    public function warn(string $event, array $context = []): void
    {
        $this->log(LogLevel::Warn, $event, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $event, array $context = []): void
    {
        $this->log(LogLevel::Error, $event, $context);
    }

    public function level(): LogLevel
    {
        return $this->level;
    }

    /** @param array<string, mixed> $context */
    private function log(LogLevel $level, string $event, array $context): void
    {
        if (!$this->level->emits($level)) {
            return;
        }

        $line = sprintf('[suqo] %-5s %s', $level->value, $event);

        if ($context !== []) {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $line .= ' ' . ($encoded === false ? '{}' : $encoded);
        }

        $stream = $this->stream ?? ($this->stream = fopen('php://stderr', 'wb') ?: null);

        if ($stream === null) {
            return;
        }

        fwrite($stream, $line . PHP_EOL);
    }
}
