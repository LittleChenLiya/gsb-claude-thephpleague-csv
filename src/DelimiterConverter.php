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
 * Converts a CSV document from a comma delimiter to a semicolon delimiter.
 *
 * The conversion is performed record by record: each record is read from the
 * Reader using its own dialect and inserted into the Writer which applies its
 * own dialect. No string replacement is performed on the raw document, so
 * quoted fields and fields containing embedded newlines are preserved.
 *
 * No header, BOM or transaction/flush policy is added by the conversion;
 * both instances keep their existing configuration.
 */
final class DelimiterConverter
{
    private const INPUT_DELIMITER = ',';
    private const OUTPUT_DELIMITER = ';';

    private function __construct()
    {
    }

    /**
     * Converts every record of a comma-delimited Reader into a semicolon-delimited Writer.
     *
     * The Reader is not consumed nor altered beyond iteration and remains usable
     * after the conversion. The method returns the number of converted records.
     *
     * @throws InvalidArgument If the Reader delimiter is not a comma or the Writer delimiter is not a semicolon
     * @throws CannotInsertRecord If a record can not be inserted into the Writer
     * @throws Exception If a record can not be inserted into the Writer
     */
    public static function commaToSemicolon(Reader $reader, Writer $writer): int
    {
        self::INPUT_DELIMITER === $reader->getDelimiter()
            || throw InvalidArgument::dueToInvalidDelimiterCharacter($reader->getDelimiter(), __METHOD__);
        self::OUTPUT_DELIMITER === $writer->getDelimiter()
            || throw InvalidArgument::dueToInvalidDelimiterCharacter($writer->getDelimiter(), __METHOD__);

        $count = 0;
        foreach ($reader->getRecords() as $record) {
            $writer->insertOne($record);
            ++$count;
        }

        return $count;
    }
}
