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

use Throwable;

/**
 * Thrown when a data is not added to the Csv Document.
 */
class CannotInsertRecord extends Exception
{
    /** The record submitted for insertion. */
    protected array $record = [];
    /** Validator which did not validate the data. */
    protected string $name = '';
    /** The number of records already written before a batch insertion failed. */
    protected int $writtenCount = 0;

    /**
     * Creates an Exception from a record insertion into a stream.
     */
    public static function triggerOnInsertion(array $record): self
    {
        $exception = new self('Unable to write record to the CSV document');
        $exception->record = $record;

        return $exception;
    }

    /**
     * Creates an Exception from a Record Validation.
     */
    public static function triggerOnValidation(string $name, array $record): self
    {
        $exception = new self('Record validation failed');
        $exception->name = $name;
        $exception->record = $record;

        return $exception;
    }

    /**
     * Creates an Exception from a record formatting failure during batch pre-validation.
     */
    public static function triggerOnBatchFormatting(array $record, Throwable $previous): self
    {
        $exception = new self('Record formatting failed during batch pre-validation; no record was written', 0, $previous);
        $exception->record = $record;

        return $exception;
    }

    /**
     * Creates an Exception from a record insertion failure during batch writing.
     */
    public static function triggerOnBatchInsertion(array $record, int $writtenCount, ?Throwable $previous = null): self
    {
        $exception = new self('Unable to write record to the CSV document; '.$writtenCount.' record(s) already written in the current batch', 0, $previous);
        $exception->record = $record;
        $exception->writtenCount = $writtenCount;

        return $exception;
    }

    /**
     * Returns the number of records already written before the batch insertion failed.
     */
    public function getWrittenCount(): int
    {
        return $this->writtenCount;
    }

    /**
     * Returns the validator name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the invalid data submitted.
     */
    public function getRecord(): array
    {
        return $this->record;
    }
}
