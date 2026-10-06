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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DelimiterConverterTest extends TestCase
{
    #[Test]
    public function it_converts_a_comma_delimited_document_into_a_semicolon_delimited_document(): void
    {
        $document = '"Smith, John","He said ""hello""",42'."\n"
            .'"line1'."\n".'line2","plain",7'."\n"
            .'"a;b;c","quote: ""x""",9';

        $reader = Reader::fromString($document);
        $records = [...$reader->getRecords()];

        $writer = Writer::fromString();
        $writer->setDelimiter(';');

        $converter = new DelimiterConverter();
        $count = $converter->convert($reader, $writer);

        self::assertSame(3, $count);

        $converted = $writer->toString();
        self::assertStringContainsString('"Smith, John";"He said ""hello""";42', $converted);
        self::assertStringContainsString('"line1'."\n".'line2";plain;7', $converted);
        self::assertStringContainsString('"a;b;c";"quote: ""x""";9', $converted);

        $target = Reader::fromString($converted);
        $target->setDelimiter(';');

        self::assertSame($records, [...$target->getRecords()]);
    }

    #[Test]
    public function it_preserves_the_original_reader_usage_after_conversion(): void
    {
        $document = '"Smith, John","He said ""hello""",42'."\n"
            .'"line1'."\n".'line2","plain",7'."\n"
            .'"a;b;c","quote: ""x""",9';

        $reader = Reader::fromString($document);
        $records = [...$reader->getRecords()];

        $writer = Writer::fromString();
        $writer->setDelimiter(';');

        (new DelimiterConverter())->convert($reader, $writer);

        self::assertSame($records, [...$reader->getRecords()]);
    }

    #[Test]
    public function it_rejects_conversion_between_other_delimiters(): void
    {
        $reader = Reader::fromString('foo;bar');
        $reader->setDelimiter(';');
        $writer = Writer::fromString();
        $writer->setDelimiter(';');

        $converter = new DelimiterConverter();

        try {
            $converter->convert($reader, $writer);
            self::fail('The conversion should fail when the reader delimiter is not a comma.');
        } catch (InvalidArgument $exception) {
            self::assertStringContainsString('`,`', $exception->getMessage());
        }

        $reader = Reader::fromString('foo,bar');
        $writer = Writer::fromString();
        $writer->setDelimiter('|');

        try {
            $converter->convert($reader, $writer);
            self::fail('The conversion should fail when the writer delimiter is not a semicolon.');
        } catch (InvalidArgument $exception) {
            self::assertStringContainsString('`;`', $exception->getMessage());
        }
    }
}
