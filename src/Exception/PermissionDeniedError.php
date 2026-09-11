<?php

declare(strict_types=1);

namespace Suqo\Exception;

/**
 * §8.1 — 403 where the body is not KYC-shaped.
 *
 * openapi documents every 403 as a plain authorization failure
 * (`{"detail": "You are not authorized to access this resource."}`): the key is
 * valid, but this account may not use this endpoint. The KYC body shape
 * ({@see KycRequiredError}) is a separate, narrower case that is checked first.
 */
final class PermissionDeniedError extends SuqoError
{
}
