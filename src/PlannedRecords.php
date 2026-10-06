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

use function array_key_exists;

/**
 * A lazy result view over a Reader driven by a HeaderPlan.
 *
 * The view never modifies the Reader it was created from: every traversal
 * reads the document through a fresh iterator and applies the plan column
 * mapping without touching the Reader state.
 *
 * Records at or before the plan header offset belong to the header section
 * (preamble lines and the real header row) and are never yielded as data;
 * data records keep their original source offsets as keys.
 *
 * Short rows (missing columns) and empty values are reported separately:
 *
 * - a missing column is absent from the yielded record and listed in
 *   {@see PlannedRecords::missingColumns()};
 * - an empty value is yielded as-is and listed in
 *   {@see PlannedRecords::emptyValues()}.
 *
 * The number of records read from the source during the current or last
 * traversal is exposed via {@see PlannedRecords::readCount()} so consumers
 * can verify that the plan pre-validation did not consume an extra record
 * from the iterator.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class PlannedRecords implements IteratorAggregate
{
    private int $reads = 0;
    /** @var array<int, list<string>> missing columns report keyed by source record offset. */
    private array $missingColumns = [];
    /** @var array<int, list<string>> empty values report keyed by source record offset. */
    private array $emptyValues = [];

    /**
     * @internal Use Reader::getRecordsWithPlan() instead.
     *
     * @param Closure(): Iterator<int, array> $iteratorFactory returns a fresh source iterator on each call
     */
    public function __construct(
        private readonly Closure $iteratorFactory,
        private readonly HeaderPlan $plan,
    ) {}

    /**
     * Returns the plan this view is bound to.
     */
    public function plan(): HeaderPlan
    {
        return $this->plan;
    }

    /**
     * Returns the number of source records read during the current or last traversal.
     */
    public function readCount(): int
    {
        return $this->reads;
    }

    /**
     * Returns the missing columns report, keyed by source record offset.
     *
     * A column is reported missing when the source record is shorter than
     * the plan header; the column is absent from the yielded record.
     *
     * @return array<int, list<string>>
     */
    public function missingColumns(): array
    {
        return $this->missingColumns;
    }

    /**
     * Returns the empty values report, keyed by source record offset.
     *
     * A value is reported empty when the field exists in the source record
     * but its value is null or the empty string; the value is still yielded
     * in the record.
     *
     * @return array<int, list<string>>
     */
    public function emptyValues(): array
    {
        return $this->emptyValues;
    }

    public function getIterator(): Iterator
    {
        $this->reads = 0;
        $this->missingColumns = [];
        $this->emptyValues = [];

        $columns = $this->plan->columns;
        $headerOffset = $this->plan->offset;
        foreach (($this->iteratorFactory)() as $offset => $record) {
            $this->reads++;
            if ($offset <= $headerOffset) {
                continue; // preamble lines and the real header row are not data records
            }

            $assoc = [];
            $missing = [];
            $empty = [];
            foreach ($columns as $index => $name) {
                if (!array_key_exists($index, $record)) {
                    $missing[] = $name;
                    continue;
                }

                $value = $record[$index];
                if (null === $value || '' === $value) {
                    $empty[] = $name;
                }

                $assoc[$name] = $value;
            }

            if ([] !== $missing) {
                $this->missingColumns[$offset] = $missing;
            }

            if ([] !== $empty) {
                $this->emptyValues[$offset] = $empty;
            }

            yield $offset => $assoc;
        }
    }
}
