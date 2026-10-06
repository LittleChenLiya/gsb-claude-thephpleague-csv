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
    private const DOCUMENT = <<<'CSV'
"Doe, John","said ""hello""",simple
"multi
line",field2,"has,comma"
plain,"quoted","line1
line2 with ""quotes"", and comma"
CSV;

    private const EXPECTED = [
        ['Doe, John', 'said "hello"', 'simple'],
        ["multi\nline", 'field2', 'has,comma'],
        ['plain', 'quoted', "line1\nline2 with \"quotes\", and comma"],
    ];

    #[Test]
    public function it_converts_a_comma_delimited_reader_into_a_semicolon_delimited_writer(): void
    {
        $reader = Reader::fromString(self::DOCUMENT);
        $writer = Writer::fromString();
        $writer->setDelimiter(';');

        $count = DelimiterConverter::commaToSemicolon($reader, $writer);

        self::assertSame(3, $count);

        $result = Reader::fromString($writer->toString());
        $result->setDelimiter(';');

        self::assertSame(self::EXPECTED, iterator_to_array($result->getRecords()));
    }

    #[Test]
    public function it_keeps_the_source_reader_usable_after_conversion(): void
    {
        $reader = Reader::fromString(self::DOCUMENT);
        $writer = Writer::fromString();
        $writer->setDelimiter(';');

        DelimiterConverter::commaToSemicolon($reader, $writer);

        self::assertSame(self::EXPECTED, iterator_to_array($reader->getRecords()));
        self::assertSame(',', $reader->getDelimiter());
    }

    #[Test]
    public function it_does_not_add_a_header_a_bom_or_change_line_endings(): void
    {
        $reader = Reader::fromString(self::DOCUMENT);
        $writer = Writer::fromString();
        $writer->setDelimiter(';');

        DelimiterConverter::commaToSemicolon($reader, $writer);

        $output = $writer->toString();
        self::assertStringStartsWith('"Doe, John";"said ""hello""";simple'."\n", $output);
        self::assertStringNotContainsString("\r\n", $output);
        self::assertStringNotContainsString(Bom::Utf8->value, $output);
    }

    #[Test]
    public function it_rejects_a_reader_which_is_not_comma_delimited(): void
    {
        $this->expectException(InvalidArgument::class);

        $reader = Reader::fromString(self::DOCUMENT);
        $reader->setDelimiter(';');
        $writer = Writer::fromString();
        $writer->setDelimiter(';');

        DelimiterConverter::commaToSemicolon($reader, $writer);
    }

    #[Test]
    public function it_rejects_a_writer_which_is_not_semicolon_delimited(): void
    {
        $this->expectException(InvalidArgument::class);

        DelimiterConverter::commaToSemicolon(Reader::fromString(self::DOCUMENT), Writer::fromString());
    }
}
