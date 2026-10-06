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

use League\Csv\DelimiterConverter;
use League\Csv\Reader;
use League\Csv\Writer;

require __DIR__.'/../autoload.php';

// A comma-delimited source document with quoted fields,
// embedded delimiters and a field containing a newline.
$source = <<<'CSV'
"Doe, John","said ""hello""",simple
"multi
line",field2,"has,comma"
plain,"quoted","line1
line2 with ""quotes"", and comma"
CSV;

$reader = Reader::fromString($source);          // default dialect: comma delimiter
$writer = Writer::fromString();
$writer->setDelimiter(';');                     // target dialect: semicolon delimiter

// Record-by-record conversion: each record is parsed with the Reader dialect
// and re-emitted with the Writer dialect. No string replacement is involved,
// no header, BOM or flush policy is added, and the Reader stays usable.
$count = DelimiterConverter::commaToSemicolon($reader, $writer);

echo $writer->toString();
echo "-- $count records converted --\n";

// Reading the converted document back with the target dialect
// returns the exact same field values.
$check = Reader::fromString($writer->toString());
$check->setDelimiter(';');
var_export(iterator_to_array($check->getRecords()));
echo "\n";
