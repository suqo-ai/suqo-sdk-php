<?php

declare(strict_types=1);

namespace Suqo\Resource;

use Generator;
use Suqo\Cancellation;
use Suqo\Constants;
use Suqo\Exception\NotImplementedError;

/**
 * §10.3 — the customers resource.
 *
 * Every operation raises {@see NotImplementedError} carrying MSG_NOT_IMPLEMENTED.
 * The resource exists so that the shape of the client is stable across the version
 * that implements it.
 *
 * openapi.yaml now specifies `GET /customers/` (`customers_list`) and
 * `GET /customers/{id}/` (`customers_read`), so the operation set below is no
 * longer a guess — it is those two operationIds minus the resource noun (N4),
 * plus the auto-paging counterpart §10.1 and §10.2 give every list. Implementing
 * them is a specification revision, not a binding decision: §10.3 is normative
 * for behaviour, and §14 forbids public surface this specification does not
 * describe. The record type they will return is already un-stubbed
 * ({@see \Suqo\Model\Customer}) because §9.4 ties that to openapi.yaml, not to
 * §10.3.
 */
final class Customers extends AbstractResource
{
    /** @throws NotImplementedError */
    public function list(
        ?int $page = null,
        ?int $pageSize = null,
        ?Cancellation $cancellation = null,
    ): never {
        throw self::unavailable();
    }

    /**
     * @return Generator<int, never>
     *
     * @throws NotImplementedError
     */
    public function autoPaging(
        ?int $page = null,
        ?int $pageSize = null,
        ?Cancellation $cancellation = null,
    ): Generator {
        throw self::unavailable();
    }

    /** @throws NotImplementedError */
    public function read(string $id, ?Cancellation $cancellation = null): never
    {
        throw self::unavailable();
    }

    private static function unavailable(): NotImplementedError
    {
        return new NotImplementedError(Constants::MSG_NOT_IMPLEMENTED);
    }
}
