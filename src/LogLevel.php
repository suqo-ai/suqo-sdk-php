<?php

declare(strict_types=1);

namespace Suqo;

/**
 * §4.1 — log level.
 */
enum LogLevel: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Warn = 'warn';
    case Error = 'error';
    case Off = 'off';

    /** Higher is more severe; Off is above every emittable level. */
    public function severity(): int
    {
        return match ($this) {
            self::Debug => 10,
            self::Info => 20,
            self::Warn => 30,
            self::Error => 40,
            self::Off => 100,
        };
    }

    public function emits(self $level): bool
    {
        if ($this === self::Off) {
            return false;
        }

        return $level->severity() >= $this->severity();
    }
}
