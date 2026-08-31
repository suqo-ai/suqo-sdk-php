<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * Internal wire-reading helpers. Tolerant by design: a field of an unexpected
 * type reads as absent rather than failing deserialisation, so a server-side
 * addition or loosening cannot break existing clients (cf. §9.3).
 *
 * @internal
 */
final class Wire
{
    /** @param array<string, mixed> $wire */
    public static function nstr(array $wire, string $key): ?string
    {
        $value = $wire[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @param array<string, mixed> $wire */
    public static function str(array $wire, string $key, string $default = ''): string
    {
        return self::nstr($wire, $key) ?? $default;
    }

    /**
     * §9.1 — decimals are strings end to end and are never parsed into a float,
     * double or decimal type inside the SDK. A numeric wire value is stringified
     * rather than rejected, but the API is specified to send strings.
     *
     * @param array<string, mixed> $wire
     */
    public static function decimal(array $wire, string $key): ?string
    {
        $value = $wire[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return null;
    }

    /** @param array<string, mixed> $wire */
    public static function nint(array $wire, string $key): ?int
    {
        $value = $wire[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /** @param array<string, mixed> $wire */
    public static function int(array $wire, string $key, int $default = 0): int
    {
        return self::nint($wire, $key) ?? $default;
    }

    /**
     * An integral count that the API may render as either a JSON number or a
     * numeric string. Counts are not monetary values, so §9.1 does not apply.
     *
     * @param array<string, mixed> $wire
     */
    public static function count(array $wire, string $key): ?int
    {
        $value = $wire[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit(ltrim($value, '-'))) {
            return (int) $value;
        }

        return null;
    }

    /** @param array<string, mixed> $wire */
    public static function nbool(array $wire, string $key): ?bool
    {
        $value = $wire[$key] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * A nested wire object, or null when absent or not an object.
     *
     * @param  array<string, mixed>      $wire
     * @return array<string, mixed>|null
     */
    public static function object(array $wire, string $key): ?array
    {
        $value = $wire[$key] ?? null;

        if (!is_array($value)) {
            return null;
        }

        if ($value !== [] && array_is_list($value)) {
            return null;
        }

        /** @var array<string, mixed> */
        return $value;
    }

    /**
     * A list of wire objects. Non-object entries are dropped.
     *
     * @param  array<string, mixed>       $wire
     * @return list<array<string, mixed>>
     */
    public static function objectList(array $wire, string $key): array
    {
        $value = $wire[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_array($item) && ($item === [] || !array_is_list($item))) {
                /** @var array<string, mixed> $item */
                $out[] = $item;
            }
        }

        return $out;
    }

    private function __construct()
    {
    }
}
