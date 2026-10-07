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

namespace League\Csv\Query;

use ArrayIterator;
use FilterIterator;
use League\Csv\MapIterator;
use Throwable;
use Traversable;

/**
 * Filters records from an iterator using a predicate.
 *
 * The iterator preserves the source record offsets: filtering neither
 * re-orders nor re-indexes the records. If the predicate fails, the
 * thrown exception reports the record offset from the source iterator
 * and not the record position in the filtered sequence, unless the
 * failure is already expressed as a {@link QueryException} in which
 * case it is rethrown unchanged. The iterator only borrows the given
 * traversable; interrupting the iteration will not close nor otherwise
 * alter the borrowed resource.
 *
 * @internal used internally to filter CSV records
 */
final class RecordFilterIterator extends FilterIterator
{
    /** @var callable The predicate to apply on all InnerIterator current value and key. */
    private $predicate;

    public function __construct(Traversable $iterator, callable $predicate)
    {
        parent::__construct(MapIterator::toIterator($iterator));
        $this->predicate = $predicate;
    }

    public static function fromIterable(iterable $iterator, callable $predicate): self
    {
        return match (true) {
            $iterator instanceof Traversable => new self($iterator, $predicate),
            is_array($iterator) => new self(new ArrayIterator($iterator), $predicate),
        };
    }

    public function accept(): bool
    {
        try {
            return ($this->predicate)($this->current(), $this->key());
        } catch (QueryException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw QueryException::dueToPredicateFailure($this->key(), $exception);
        }
    }
}
