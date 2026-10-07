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
     * Adds a small batch of records to the CSV document after validating the whole batch.
     *
     * The batch is first buffered and fully validated: each record is
     * formatted and validated, and all records must share the same field
     * count. If the batch validation fails, nothing is written to the
     * document. If the underlying write fails while flushing the batch,
     * the thrown exception reports how many records were already written.
     *
     * Only small batches with an explicit maximum size are supported.
     *
     * @see TabularDataWriter::insertAll
     *
     * @param iterable<array<null|int|float|string|Stringable>> $records a small batch of records
     * @param int $batchSize the maximum number of records the batch may contain
     *
     * @throws InvalidArgument If the batch size is invalid or the batch contains more records than declared
     * @throws CannotInsertRecord If the batch validation fails or a record can not be inserted
     * @throws Exception If the record can not be inserted
     */
    public function insertBatch(iterable $records, int $batchSize): int;

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
