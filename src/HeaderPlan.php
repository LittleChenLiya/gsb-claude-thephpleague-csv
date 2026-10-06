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
 * An explicit header offset binding plan.
 *
 * The plan captures, at creation time, the header offset and the real
 * header row of a Reader document. Original record offsets (the source
 * sequence numbers) are kept separate from the header row: the header
 * lives in the plan and is never yielded as a data record.
 *
 * The plan is bound to the Reader instance and to the header offset it
 * was created from. Once the Reader header offset changes, the plan
 * becomes stale and its column plan can no longer be reused; a new plan
 * must be prepared.
 */
final class HeaderPlan
{
    /** @var array<int, string> column names made unique by occurrence identity. */
    public readonly array $columns;

    /**
     * @param array<string> $header the real header row as read from the document
     */
    private function __construct(
        private readonly Reader $reader,
        public readonly int $offset,
        public readonly array $header,
    ) {
        $this->columns = self::resolveColumns($header);
    }

    /**
     * @internal Use Reader::prepareHeaderPlan() instead.
     *
     * @param array<string> $header
     */
    public static function bind(Reader $reader, int $offset, array $header): self
    {
        return new self($reader, $offset, $header);
    }

    /**
     * Tells whether the plan can still be used with the given reader.
     *
     * The plan is only valid for the Reader instance it was created from
     * and only while that instance keeps the same header offset. Switching
     * the header offset invalidates the bound column plan.
     */
    public function isBoundTo(Reader $reader): bool
    {
        return $this->reader === $reader && $this->offset === $reader->getHeaderOffset();
    }

    /**
     * Maps each header cell to a column name unique by occurrence identity.
     *
     * The first occurrence of a name keeps the bare name; subsequent
     * occurrences are suffixed with their occurrence number (eg `id`,
     * `id#2`, `id#3`), so duplicated header names are preserved by their
     * occurrence identity instead of being rejected or collapsed.
     *
     * @param array<string> $header
     *
     * @return array<int, string>
     */
    private static function resolveColumns(array $header): array
    {
        $occurrences = [];
        $columns = [];
        foreach ($header as $index => $name) {
            $name = (string) $name;
            $occurrence = ($occurrences[$name] ?? 0) + 1;
            $occurrences[$name] = $occurrence;
            $columns[$index] = 1 === $occurrence ? $name : $name.'#'.$occurrence;
        }

        return $columns;
    }
}
