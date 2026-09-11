<?php

declare(strict_types=1);

namespace Suqo\Exception;

/**
 * §8.1 / §10.3 — an unimplemented resource; status 0.
 *
 * Nothing raises this today: Customers, the resource it was introduced for, is
 * implemented. It is kept as the declared type for any resource added to the
 * client's shape ahead of its operations, which is the pattern §10.3 describes.
 */
final class NotImplementedError extends SuqoError
{
}
