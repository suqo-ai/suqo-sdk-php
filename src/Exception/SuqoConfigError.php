<?php

declare(strict_types=1);

namespace Suqo\Exception;

/**
 * §8.1 — raised during construction, before any request exists. It therefore
 * carries no status, request id or body.
 *
 * Derives from {@see SuqoError} so that a single `catch (SuqoError)` is total
 * across the SDK, matching the TypeScript binding, where `SuqoConfigError`
 * extends the same root. An earlier revision extended \InvalidArgumentException
 * instead; that is the more literal PHP idiom for a construction-time argument
 * fault, but it split the hierarchy and surprised callers who had written the
 * obvious catch-all.
 */
final class SuqoConfigError extends SuqoError
{
}
