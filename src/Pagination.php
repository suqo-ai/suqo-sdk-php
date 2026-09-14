<?php

declare(strict_types=1);

namespace Suqo;

use Generator;
use Suqo\Exception\CancelledError;
use Suqo\Exception\SuqoError;
use Suqo\Http\Transport;
use Suqo\Model\Wire;

/**
 * §11.2 — lazy iteration across pages.
 *
 * B4: the binding's lazy-iteration primitive is a PHP Generator. A page is
 * fetched only once the consumer has exhausted the previous one; nothing is
 * accumulated.
 */
final class Pagination
{
    /**
     * §11.2 — the most pages one iteration will follow before giving up.
     *
     * `next` is server-supplied and followed without a bound otherwise, so a
     * link that points at its own page — a backend bug, or a tampered response —
     * would spin forever, issuing requests the caller never asked for. The cap
     * is far above any real collection; reaching it means the server is not
     * advancing, not that the data ran out. Matches the TypeScript binding.
     */
    public const MAX_PAGES = 10_000;

    /**
     * @template T
     *
     * @param  callable(array<string, mixed>): T $factory Applies the §3 read-direction
     *         rename, since it constructs the record.
     * @return Generator<int, T>
     *
     * @throws Exception\SuqoError
     */
    public static function autoPage(
        Transport $transport,
        string $firstUrl,
        callable $factory,
        ?Cancellation $cancellation = null,
    ): Generator {
        $cancellation ??= Cancellation::none();
        $url = $firstUrl;
        $pagesSeen = 0;

        while ($url !== null) {
            if (++$pagesSeen > self::MAX_PAGES) {
                throw new SuqoError(sprintf(
                    'Auto-paging exceeded %d pages without reaching the end of the collection. '
                    . 'The server is not advancing: check for a next link that repeats a page.',
                    self::MAX_PAGES,
                ));
            }

            // Checked before each fetch, so a cancelled iteration stops at the
            // current page boundary rather than after draining the collection.
            if ($cancellation->isCancelled()) {
                throw new CancelledError('Request cancelled by caller.');
            }

            // §6.6 — the same retry policy as any other request.
            $response = $transport->getAbsolute($url, $cancellation);
            $page = $response->object();

            foreach (Wire::objectList($page, 'results') as $record) {
                yield $factory($record);
            }

            $url = Wire::nstr($page, 'next');
        }
    }

    private function __construct()
    {
    }
}
