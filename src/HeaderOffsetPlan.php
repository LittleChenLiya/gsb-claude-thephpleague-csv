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

use Closure;
use Iterator;
use IteratorAggregate;

use function array_values;

/**
 * An explicit header offset binding plan.
 *
 * The plan binds a column plan to an explicitly given header offset:
 *
 * - the original record offsets are kept as iterator keys while the real
 *   header row is kept apart and exposed as HeaderOffsetColumn instances;
 * - duplicate header names are preserved: each column keeps its occurrence
 *   identity (eg. `name#0`, `name#1`);
 * - short rows (missing columns) and empty values are reported separately
 *   by the returned HeaderOffsetRecord views;
 * - the plan explicitly owns the header offset: the Reader MUST stay
 *   unbound (Reader::getHeaderOffset() returns null). As soon as a header
 *   offset is set on the Reader, previously bound plans are rejected and
 *   can not be reused;
 * - the plan never modifies the Reader and Reader::getRecords() is left
 *   untouched;
 * - resolving the header row never consumes an extra record from the
 *   iterator returned to the consumer: the header is either captured
 *   inline during the iteration pass or resolved on a separate fresh pass
 *   which stops exactly on the header row. The number of records read
 *   from the source is auditable via sourceReads().
 *
 * @implements IteratorAggregate<int, HeaderOffsetRecord>
 */
final class HeaderOffsetPlan implements IteratorAggregate
{
    /** @var list<HeaderOffsetColumn>|null */
    private ?array $columns = null;
    private int $sourceReads = 0;
    /** @var Closure(): list<HeaderOffsetColumn> */
    private readonly Closure $columnsResolver;

    private function __construct(
        private readonly Reader $reader,
        private readonly int $offset,
    ) {
        $this->columnsResolver = fn (): array => $this->columns();
    }

    /**
     * Binds a column plan to the explicitly given header offset.
     *
     * @throws InvalidArgument If the offset is a negative integer
     * @throws InvalidArgument If the reader already has a header offset set
     */
    public static function bind(Reader $reader, int $offset): self
    {
        0 <= $offset || throw InvalidArgument::dueToInvalidHeaderOffset($offset, __METHOD__);

        $plan = new self($reader, $offset);
        $plan->assertReusable();

        return $plan;
    }

    /**
     * Returns the bound header offset.
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Returns the number of records read from the source by the plan.
     */
    public function sourceReads(): int
    {
        return $this->sourceReads;
    }

    /**
     * Returns the bound header columns, duplicates included.
     *
     * The header row is resolved on a fresh pass which stops exactly on
     * the header row: no extra record is consumed from the iterators
     * returned by getIterator().
     *
     * @throws InvalidArgument If a header offset has been set on the reader since binding
     * @throws SyntaxError If no record exists at the bound offset
     *
     * @return list<HeaderOffsetColumn>
     */
    public function columns(): array
    {
        if (null === $this->columns) {
            $this->assertReusable();
            foreach ($this->readSource() as $offset => $record) {
                if ($offset === $this->offset) {
                    $this->columns = self::buildColumns($record, $this->offset);
                    break;
                }
            }

            null === $this->columns && throw SyntaxError::dueToHeaderNotFound($this->offset);
        }

        return $this->columns;
    }

    /**
     * Returns the records keyed by their original source offset.
     *
     * The record at the bound header offset is kept apart and is not
     * returned. The header row, when not already resolved, is captured
     * inline during the iteration: the source is then read exactly once.
     *
     * @throws InvalidArgument If a header offset has been set on the reader since binding
     * @throws SyntaxError If no record exists at the bound offset
     *
     * @return Iterator<int, HeaderOffsetRecord>
     */
    public function getIterator(): Iterator
    {
        $this->assertReusable();

        return $this->iterateRecords();
    }

    /**
     * @return Iterator<int, HeaderOffsetRecord>
     */
    private function iterateRecords(): Iterator
    {
        foreach ($this->readSource() as $offset => $record) {
            if ($offset === $this->offset) {
                $this->columns ??= self::buildColumns($record, $this->offset);
                continue;
            }

            yield $offset => new HeaderOffsetRecord($offset, $record, $this->columnsResolver);
        }

        null === $this->columns && throw SyntaxError::dueToHeaderNotFound($this->offset);
    }

    /**
     * @return Iterator<int, array>
     */
    private function readSource(): Iterator
    {
        $this->assertReusable();

        foreach ($this->reader->getRecords() as $offset => $record) {
            ++$this->sourceReads;

            yield $offset => $record;
        }
    }

    /**
     * @throws InvalidArgument If the reader already has a header offset set
     */
    private function assertReusable(): void
    {
        $readerOffset = $this->reader->getHeaderOffset();
        null === $readerOffset || throw new InvalidArgument(
            'The header offset plan bound to `'.$this->offset.'` can not be reused: the reader header offset is `'.$readerOffset.'`; bind a new plan once the reader header offset is reset to null.'
        );
    }

    /**
     * @throws SyntaxError If the header record is empty
     *
     * @return list<HeaderOffsetColumn>
     */
    private static function buildColumns(array $record, int $offset): array
    {
        if ([] === $record || [null] === $record || [false] === $record) {
            throw SyntaxError::dueToHeaderNotFound($offset);
        }

        $seen = [];
        $columns = [];
        foreach (array_values($record) as $position => $value) {
            $name = (string) $value;
            $occurrence = $seen[$name] ?? 0;
            $seen[$name] = $occurrence + 1;
            $columns[] = new HeaderOffsetColumn($name, $occurrence, $position);
        }

        return $columns;
    }
}
