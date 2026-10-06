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

/**
 * A single header column occurrence bound to a HeaderOffsetPlan.
 *
 * Because a header row may contain duplicate names, each column is
 * identified by its name AND its occurrence index among the columns
 * sharing that name, eg. `name#0`, `name#1`. The position is the
 * column index in the header row.
 */
final class HeaderOffsetColumn
{
    /**
     * @param string $name the column name as found in the header row
     * @param int $occurrence the 0-based occurrence index among columns sharing the same name
     * @param int $position the 0-based column position in the header row
     */
    public function __construct(
        public readonly string $name,
        public readonly int $occurrence,
        public readonly int $position,
    ) {
    }

    /**
     * Returns the occurrence identity of the column, eg. `name#1`.
     */
    public function identity(): string
    {
        return $this->name.'#'.$this->occurrence;
    }
}
