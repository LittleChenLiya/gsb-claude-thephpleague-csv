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

use function array_key_exists;
use function count;

/**
 * A record view bound to a HeaderOffsetPlan.
 *
 * The view keeps the original record offset and the record as read from
 * the source. Short rows (missing columns) and empty values are reported
 * separately: a missing column is absent from the record while an empty
 * value is present but holds an empty string or null.
 */
final class HeaderOffsetRecord
{
    /**
     * @param int $offset the original record offset in the source
     * @param array $record the record as read from the source
     * @param Closure(): list<HeaderOffsetColumn> $columnsResolver
     */
    public function __construct(
        public readonly int $offset,
        private readonly array $record,
        private readonly Closure $columnsResolver,
    ) {
    }

    /**
     * Returns the record as read from the source, without any header mapping.
     */
    public function raw(): array
    {
        return $this->record;
    }

    /**
     * Returns the values aligned on the header column positions.
     *
     * Only positions covered by the header AND present in the record are
     * returned: a short row leaves the missing positions absent while an
     * empty value is returned as an empty string, so both cases can be
     * told apart with array_key_exists().
     *
     * @return array<int, string|null>
     */
    public function values(): array
    {
        $values = [];
        foreach ($this->columns() as $column) {
            if (array_key_exists($column->position, $this->record)) {
                $values[$column->position] = $this->record[$column->position];
            }
        }

        return $values;
    }

    /**
     * Returns the value of a column occurrence or null when the record is too short.
     *
     * @throws InvalidArgument If the column occurrence is unknown
     */
    public function get(string $name, int $occurrence = 0): ?string
    {
        0 <= $occurrence || throw new InvalidArgument(__METHOD__.'() expects the occurrence to be greater or equal to 0; `'.$occurrence.'` given.');

        foreach ($this->columns() as $column) {
            if ($column->name === $name && $column->occurrence === $occurrence) {
                return $this->record[$column->position] ?? null;
            }
        }

        throw new InvalidArgument(__METHOD__.'() expects a known header column occurrence; `'.$name.'#'.$occurrence.'` given.');
    }

    /**
     * Returns the header columns absent from the record because it is shorter than the header.
     *
     * @return list<HeaderOffsetColumn>
     */
    public function missingColumns(): array
    {
        $missing = [];
        foreach ($this->columns() as $column) {
            if (!array_key_exists($column->position, $this->record)) {
                $missing[] = $column;
            }
        }

        return $missing;
    }

    /**
     * Returns the header columns present in the record but holding an empty value.
     *
     * @return list<HeaderOffsetColumn>
     */
    public function emptyColumns(): array
    {
        $empty = [];
        foreach ($this->columns() as $column) {
            if (array_key_exists($column->position, $this->record)
                && (null === $this->record[$column->position] || '' === $this->record[$column->position])) {
                $empty[] = $column;
            }
        }

        return $empty;
    }

    /**
     * Returns the values located beyond the header columns, keyed by their position.
     *
     * @return array<int, mixed>
     */
    public function extraValues(): array
    {
        $extra = [];
        $positions = count($this->columns());
        foreach ($this->record as $position => $value) {
            if ($position >= $positions) {
                $extra[$position] = $value;
            }
        }

        return $extra;
    }

    /**
     * Tells whether the record is shorter than the header.
     */
    public function hasMissingColumns(): bool
    {
        return [] !== $this->missingColumns();
    }

    /**
     * Tells whether the record contains empty values.
     */
    public function hasEmptyValues(): bool
    {
        return [] !== $this->emptyColumns();
    }

    /**
     * @return list<HeaderOffsetColumn>
     */
    private function columns(): array
    {
        return ($this->columnsResolver)();
    }
}
