<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv;

use CallbackFilterIterator;
use League\Csv\Query\QueryException;
use Throwable;
use Traversable;

/**
 * Filters records from an iterator using a predicate.
 *
 * The iterator yields the records unchanged, keeps the original record
 * offsets as keys and does not re-order the records. If the predicate
 * fails, the thrown exception reports the original record offset instead
 * of the filtered sequence position. {@link QueryException} instances
 * are already domain exceptions and are re-thrown unchanged.
 *
 * The iterator does not take ownership of the inner iterator; interrupting
 * the iteration will not close any borrowed resource.
 *
 * @internal used internally to filter CSV records
 */
final class RecordFilterIterator extends CallbackFilterIterator
{
    /**
     * @param Traversable<array-key, mixed> $iterator
     * @param callable(mixed, array-key): bool $predicate
     */
    public function __construct(Traversable $iterator, callable $predicate)
    {
        parent::__construct(
            MapIterator::toIterator($iterator),
            function (mixed $record, string|int $offset) use ($predicate): bool {
                try {
                    return $predicate($record, $offset);
                } catch (QueryException $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    throw QueryException::dueToPredicateFailure($offset, $exception);
                }
            }
        );
    }
}
