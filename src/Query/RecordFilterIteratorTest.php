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

namespace League\Csv\Query;

use ArrayIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function iterator_to_array;

final class RecordFilterIteratorTest extends TestCase
{
    #[Test]
    public function it_filters_records_without_reordering_or_reindexing_them(): void
    {
        $iterable = [
            2 => ['volume' => 67, 'edition' => 2],
            5 => ['volume' => 86, 'edition' => 1],
            7 => ['volume' => 85, 'edition' => 6],
            11 => ['volume' => 98, 'edition' => 2],
        ];

        $iterator = RecordFilterIterator::fromIterable(
            $iterable,
            fn (array $record, int $offset): bool => $record['volume'] > 80
        );

        self::assertSame([
            5 => ['volume' => 86, 'edition' => 1],
            7 => ['volume' => 85, 'edition' => 6],
            11 => ['volume' => 98, 'edition' => 2],
        ], iterator_to_array($iterator));
    }

    #[Test]
    public function it_reports_the_source_record_offset_when_the_predicate_fails(): void
    {
        $predicate = function (array $record, int $offset): bool {
            if (3 === $offset) {
                throw new RuntimeException('boom');
            }

            return 0 === $offset % 2;
        };

        $iterator = RecordFilterIterator::fromIterable([
            ['volume' => 67],
            ['volume' => 86],
            ['volume' => 85],
            ['volume' => 98],
            ['volume' => 86],
        ], $predicate);

        try {
            iterator_to_array($iterator);
            self::fail('The predicate failure should have been reported.');
        } catch (QueryException $exception) {
            // the reported offset is the source record offset `3`
            // not the record position `2` in the filtered sequence
            self::assertStringContainsString('offset `3`', $exception->getMessage());
            self::assertStringNotContainsString('offset `2`', $exception->getMessage());
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
            self::assertSame('boom', $exception->getPrevious()->getMessage());
        }
    }

    #[Test]
    public function it_reports_string_offsets_when_the_predicate_fails(): void
    {
        $iterator = RecordFilterIterator::fromIterable(
            ['first' => 1, 'second' => 2],
            fn (int $value, string $offset): bool => 'second' !== $offset || throw new RuntimeException('boom')
        );

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('offset `second`');

        iterator_to_array($iterator);
    }

    #[Test]
    public function it_rethrows_query_exceptions_unchanged(): void
    {
        $queryException = QueryException::dueToUnknownColumn('Ville', []);
        $iterator = RecordFilterIterator::fromIterable(
            [['volume' => 67]],
            fn (array $record, int $offset): bool => throw $queryException
        );

        try {
            iterator_to_array($iterator);
            self::fail('The query exception should have been rethrown.');
        } catch (QueryException $exception) {
            self::assertSame($queryException, $exception);
        }
    }

    #[Test]
    public function it_does_not_close_the_borrowed_iterator_when_the_iteration_is_interrupted(): void
    {
        $inner = new ArrayIterator([1, 2, 3, 4, 5]);
        $iterator = new RecordFilterIterator($inner, fn (int $value, int $offset): bool => true);

        foreach ($iterator as $value) {
            break;
        }

        self::assertTrue($inner->valid());
        self::assertSame([1, 2, 3, 4, 5], iterator_to_array($iterator));
    }
}
