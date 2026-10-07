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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function fopen;
use function in_array;
use function stream_get_wrappers;
use function stream_wrapper_register;
use function stream_wrapper_unregister;
use function strlen;
use function tmpfile;

#[Group('writer')]
final class WriterBatchTest extends TestCase
{
    private const RECORDS = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane', 'doe', 'jane.doe@example.com'],
        ['john', 'jane', 'john.jane@example.com'],
    ];

    private Writer $csv;

    protected function setUp(): void
    {
        $this->csv = Writer::from(tmpfile());
    }

    protected function tearDown(): void
    {
        unset($this->csv);
        if (in_array(FailingWriteStream::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(FailingWriteStream::SCHEME);
        }
    }

    public function testItWritesAValidSmallBatch(): void
    {
        $expected = "john,doe,john.doe@example.com\n"
            ."jane,doe,jane.doe@example.com\n"
            ."john,jane,john.jane@example.com\n";

        $bytes = $this->csv->insertBatch(self::RECORDS, 3);

        self::assertSame(strlen($expected), $bytes);
        self::assertSame($expected, $this->csv->toString());
    }

    public function testItWritesNothingWhenARecordFieldCountDiffers(): void
    {
        $records = self::RECORDS;
        $records[1] = ['jane', 'doe'];

        try {
            $this->csv->insertBatch($records, 3);
            self::fail('An exception should have been thrown.');
        } catch (CannotInsertRecord $exception) {
            self::assertSame('record_field_count', $exception->getName());
            self::assertSame(['jane', 'doe'], $exception->getRecord());
            self::assertSame(0, $exception->getWrittenCount());
        }

        self::assertSame('', $this->csv->toString());
    }

    public function testItWritesNothingWhenTheFormatterThrows(): void
    {
        $this->csv->addFormatter(function (array $record): array {
            'jane' === $record[0] || throw new RuntimeException('formatter failure');

            return $record;
        });

        try {
            $this->csv->insertBatch(self::RECORDS, 3);
            self::fail('An exception should have been thrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('formatter failure', $exception->getMessage());
        }

        self::assertSame('', $this->csv->toString());
    }

    public function testItReportsWrittenRecordsWhenTheUnderlyingWriteFails(): void
    {
        stream_wrapper_register(FailingWriteStream::SCHEME, FailingWriteStream::class);
        FailingWriteStream::$failOnWrite = 2;

        /** @var resource $stream */
        $stream = fopen(FailingWriteStream::SCHEME.'://records.csv', 'w');
        $csv = Writer::from($stream);

        try {
            $csv->insertBatch(self::RECORDS, 3);
            self::fail('An exception should have been thrown.');
        } catch (CannotInsertRecord $exception) {
            self::assertSame(1, $exception->getWrittenCount());
            self::assertSame(['jane', 'doe', 'jane.doe@example.com'], $exception->getRecord());

            $cause = $exception->getPrevious();
            self::assertInstanceOf(CannotInsertRecord::class, $cause);
            self::assertSame('Unable to write record to the CSV document', $cause->getMessage());
            self::assertSame(['jane', 'doe', 'jane.doe@example.com'], $cause->getRecord());
        }

        self::assertSame("john,doe,john.doe@example.com\n", FailingWriteStream::$written);
    }

    public function testItRejectsAnInvalidBatchSize(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->csv->insertBatch(self::RECORDS, 0);
    }

    public function testItRejectsBatchesLargerThanTheDeclaredSize(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->csv->insertBatch(self::RECORDS, 2);
    }
}

/**
 * Stream wrapper fixture whose Nth write call fails.
 *
 * @internal only used by WriterBatchTest
 */
final class FailingWriteStream
{
    public const SCHEME = 'league-csv-failing-write';

    /** @var resource|null */
    public $context;
    /** The 1-based write call number that must fail; -1 disables the failure. */
    public static int $failOnWrite = -1;
    /** The data actually written to the stream. */
    public static string $written = '';
    private int $writeCount = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        self::$written = '';
        $this->writeCount = 0;

        return true;
    }

    public function stream_write(string $data): int|false
    {
        ++$this->writeCount;
        if ($this->writeCount === self::$failOnWrite) {
            return false;
        }

        self::$written .= $data;

        return strlen($data);
    }

    public function stream_close(): void
    {
    }

    public function stream_eof(): bool
    {
        return true;
    }

    public function stream_flush(): bool
    {
        return true;
    }
}
