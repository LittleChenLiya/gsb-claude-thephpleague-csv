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

use ArrayIterator;
use League\Csv\Query\QueryException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_keys;
use function fopen;
use function fwrite;
use function is_resource;
use function iterator_to_array;

#[Group('csv')]
final class RecordFilterIteratorTest extends TestCase
{
    public function testItYieldsOnlyMatchingRecordsWithTheirOriginalOffsets(): void
    {
        $iterator = new RecordFilterIterator(
            new ArrayIterator([3 => ['a'], 5 => ['b'], 8 => ['c']]),
            fn (array $record, int $offset): bool => 0 === $offset % 2
        );

        self::assertSame([8 => ['c']], iterator_to_array($iterator));
    }

    public function testItWrapsPredicateFailureWithTheOriginalRecordOffset(): void
    {
        $iterator = new RecordFilterIterator(
            new ArrayIterator([1 => ['ok'], 4 => ['boom']]),
            function (array $record): bool {
                ['boom'] === $record && throw new RuntimeException('Filtering failed.');

                return true;
            }
        );

        try {
            iterator_to_array($iterator);
            self::fail('An exception should have been thrown.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('offset `4`', $exception->getMessage());
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }
    }

    public function testItLetsQueryExceptionBubbleUpUnchanged(): void
    {
        $queryException = QueryException::dueToUnknownColumn('city', []);
        $iterator = new RecordFilterIterator(
            new ArrayIterator([1 => ['ok'], 4 => ['boom']]),
            fn (array $record): bool => ['boom'] === $record ? throw $queryException : true
        );

        try {
            iterator_to_array($iterator);
            self::fail('An exception should have been thrown.');
        } catch (QueryException $exception) {
            self::assertSame($queryException, $exception);
        }
    }

    public function testItFiltersCsvRecordsAndReportsTheOriginalRecordOffsetOnFailure(): void
    {
        $resource = self::createResource();
        $reader = Reader::fromStream($resource)->setHeaderOffset(0);

        $predicate = function (array $record, int $offset): bool {
            return match ($record['name']) {
                'boom' => throw new RuntimeException('Unable to filter the record.'),
                'foo', 'bar' => false,
                default => true,
            };
        };

        $records = [];
        try {
            foreach ($reader->filter($predicate) as $offset => $record) {
                $records[$offset] = $record;
            }
            self::fail('An exception should have been thrown.');
        } catch (QueryException $exception) {
            // the reported offset is the original record offset
            // and not the filtered sequence position (2 records were already accepted)
            self::assertStringContainsString('offset `4`', $exception->getMessage());
            self::assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }

        // 2 records out of 5 are kept, keyed by their original offsets,
        // with the header mapping preserved and no re-ordering
        self::assertSame([1, 2], array_keys($records));
        self::assertSame(['name' => 'john', 'email' => 'john.doe@example.com'], $records[1]);
        self::assertSame(['name' => 'jane', 'email' => 'jane.doe@example.com'], $records[2]);

        // the borrowed resource is still readable after the failure
        self::assertTrue(is_resource($resource));
        self::assertSame(5, $reader->count());
    }

    public function testInterruptingTheIterationDoesNotCloseTheBorrowedResource(): void
    {
        $resource = self::createResource();
        $reader = Reader::fromStream($resource)->setHeaderOffset(0);

        foreach ($reader->filter(fn (array $record): bool => true) as $record) {
            break;
        }

        self::assertTrue(is_resource($resource));
        self::assertSame(5, $reader->count());
        self::assertSame(['name' => 'john', 'email' => 'john.doe@example.com'], $reader->first());
    }

    /**
     * @return resource
     */
    private static function createResource()
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        fwrite($resource, <<<'CSV'
            name,email
            john,john.doe@example.com
            jane,jane.doe@example.com
            foo,foo.bar@example.com
            boom,boom@example.com
            bar,bar.baz@example.com
            CSV);

        return $resource;
    }
}
