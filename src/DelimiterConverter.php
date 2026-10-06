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
 * Converts a CSV document from one delimiter to another.
 *
 * The conversion relies on the existing dialect handling of the Reader
 * and the Writer: each record is parsed with the Reader delimiter,
 * enclosure and escape characters, then re-encoded with the Writer
 * ones. No string replacement is performed on the document content.
 *
 * Only comma (',') to semicolon (';') conversion is supported.
 */
final class DelimiterConverter
{
    public const SOURCE_DELIMITER = ',';
    public const TARGET_DELIMITER = ';';

    /**
     * Converts the CSV document read by $reader into the CSV document
     * written by $writer, record by record.
     *
     * No header, BOM or any other content is added to the writer: only
     * the records yielded by the reader are inserted, using both sides
     * existing dialect handling.
     *
     * @return int the number of records converted
     *
     * @throws InvalidArgument if the reader delimiter is not a comma or
     *                         the writer delimiter is not a semicolon
     */
    public function convert(Reader $reader, Writer $writer): int
    {
        self::SOURCE_DELIMITER === $reader->getDelimiter() || throw InvalidArgument::dueToUnsupportedDelimiterConversion(
            $reader->getDelimiter(),
            self::SOURCE_DELIMITER,
            __METHOD__,
        );
        self::TARGET_DELIMITER === $writer->getDelimiter() || throw InvalidArgument::dueToUnsupportedDelimiterConversion(
            $writer->getDelimiter(),
            self::TARGET_DELIMITER,
            __METHOD__,
        );

        $count = 0;
        foreach ($reader->getRecords() as $record) {
            $writer->insertOne($record);
            ++$count;
        }

        return $count;
    }
}
