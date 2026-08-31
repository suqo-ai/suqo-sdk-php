<?php

declare(strict_types=1);

namespace Suqo;

use Generator;
use Suqo\Exception\CancelledError;
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

        while ($url !== null) {
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
