<?php

declare(strict_types=1);

namespace Suqo\Resource;

use Generator;
use Suqo\Cancellation;
use Suqo\Endpoints;
use Suqo\Model\Page;
use Suqo\Model\Payout;
use Suqo\Pagination;

/**
 * The payouts resource — openapi's `payouts_*` operations.
 *
 * A payout moves every due transaction to your verified bank account, less
 * gateway fees, the platform fee and unbilled SMS usage. The bank account is
 * managed through {@see BankAccounts}.
 */
final class Payouts extends AbstractResource
{
    /**
     * GET payouts — newest first.
     *
     * @return Page<Payout>
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function list(
        ?int $page = null,
        ?int $pageSize = null,
        ?Cancellation $cancellation = null,
    ): Page {
        $response = $this->transport->request(
            'GET',
            Endpoints::PAYOUTS,
            null,
            self::pageQuery($page, $pageSize),
            $cancellation,
        );

        return Page::fromWire(
            $response->object(),
            static fn (array $record): Payout => Payout::fromWire($record),
        );
    }

    /**
     * A lazy sequence of payouts across every page.
     *
     * @return Generator<int, Payout>
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function autoPaging(
        ?int $page = null,
        ?int $pageSize = null,
        ?Cancellation $cancellation = null,
    ): Generator {
        return Pagination::autoPage(
            $this->transport,
            $this->transport->url(Endpoints::PAYOUTS, self::pageQuery($page, $pageSize)),
            static fn (array $record): Payout => Payout::fromWire($record),
            $cancellation,
        );
    }

    /**
     * POST payouts — request a payout of everything currently due. Takes no
     * body, so none is sent and no Content-Type header is set (§6.3).
     *
     * Only one payout may be pending at a time, so a repeat after success
     * answers 400 (`code: pending_payout_request_exists`) rather than paying
     * twice. Being a write, it is not retried for you.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function create(?Cancellation $cancellation = null): Payout
    {
        $response = $this->transport->request(
            'POST',
            Endpoints::PAYOUTS,
            null,
            [],
            $cancellation,
        );

        return Payout::fromWire($response->object());
    }

    /**
     * GET one payout — to track whether it is still `PENDING` or `COMPLETED`.
     *
     * @param string $id The `payoutId` (`PYT-…`) from a list or create.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function read(string $id, ?Cancellation $cancellation = null): Payout
    {
        $response = $this->transport->request(
            'GET',
            Endpoints::payoutRead($id),
            null,
            [],
            $cancellation,
        );

        return Payout::fromWire($response->object());
    }
}
