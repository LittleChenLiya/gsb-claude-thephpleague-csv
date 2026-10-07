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

use Stringable;

/**
 * A class to insert records into a CSV Document.
 */
interface TabularDataWriter
{
    /**
     * Adds multiple records to the CSV document.
     *
     * @see TabularDataWriter::insertOne
     *
     * @param iterable<array<null|int|float|string|Stringable>> $records
     *
     * @throws CannotInsertRecord If the record can not be inserted
     * @throws Exception If the record can not be inserted
     */
    public function insertAll(iterable $records): int;

    /**
     * Adds a small batch of records to the CSV document.
     *
     * The whole batch is pre-validated (field count consistency, record
     * formatters and validators) before anything is written: if the
     * pre-validation fails, no record of the batch is written at all.
     * If the underlying write fails while the batch is being written,
     * the thrown exception still reports how many records of the batch
     * were already written.
     *
     * Only batch input with a known size (ie. arrays) is supported.
     *
     * @param array<array<null|int|float|string|Stringable>> $records
     *
     * @throws CannotInsertRecord If the batch can not be validated or inserted
     * @throws Exception If the batch can not be inserted
     */
    public function insertBatch(array $records): int;

    /**
     * Adds a single record to a CSV document.
     *
     * A record is an array that can contain scalar type values, NULL values
     * or objects implementing the __toString method.
     *
     * @param array<mixed> $record
     *
     * @throws CannotInsertRecord If the record can not be inserted
     * @throws Exception If the record can not be inserted
     */
    public function insertOne(array $record): int;
}
