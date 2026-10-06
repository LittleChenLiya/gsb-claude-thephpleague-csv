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

use function array_keys;
use function array_map;
use function iterator_to_array;

#[Group('csv')]
final class HeaderOffsetPlanTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../test_files/header_offset_plan.csv';

    private function reader(): Reader
    {
        return Reader::fromPath(self::FIXTURE);
    }

    /** @return array<int, HeaderOffsetRecord> */
    private function records(HeaderOffsetPlan $plan): array
    {
        return iterator_to_array($plan->getIterator());
    }

    /** @return list<string> */
    private function identities(array $columns): array
    {
        return array_map(fn (HeaderOffsetColumn $column): string => $column->identity(), $columns);
    }

    public function testBindRejectsANegativeOffset(): void
    {
        $this->expectException(InvalidArgument::class);

        HeaderOffsetPlan::bind($this->reader(), -1);
    }

    public function testBindRejectsAReaderWithAHeaderOffset(): void
    {
        $reader = $this->reader();
        $reader->setHeaderOffset(2);

        $this->expectException(InvalidArgument::class);

        HeaderOffsetPlan::bind($reader, 2);
    }

    public function testRecordsKeepTheirOriginalSourceOffset(): void
    {
        $plan = HeaderOffsetPlan::bind($this->reader(), 2);

        $records = $this->records($plan);

        self::assertSame(2, $plan->offset());
        self::assertSame([0, 1, 3, 4], array_keys($records));
        self::assertSame(['# quarterly export'], $records[0]->raw());
        self::assertSame(['# unverified rows'], $records[1]->raw());
        self::assertSame(['alice', 'alice@example.net', ''], $records[3]->raw());
        self::assertSame(['bob', 'bob@example.net'], $records[4]->raw());
    }

    public function testDuplicateHeaderNamesKeepTheirOccurrenceIdentity(): void
    {
        $plan = HeaderOffsetPlan::bind($this->reader(), 2);

        self::assertSame(['name#0', 'email#0', 'name#1'], $this->identities($plan->columns()));
        self::assertSame([0, 1, 2], array_map(fn (HeaderOffsetColumn $column): int => $column->position, $plan->columns()));

        $records = $this->records($plan);
        self::assertSame('alice', $records[3]->get('name'));
        self::assertSame('', $records[3]->get('name', 1));
        self::assertSame('alice@example.net', $records[3]->get('email'));
    }

    public function testUnknownColumnOccurrenceIsRejected(): void
    {
        $records = $this->records(HeaderOffsetPlan::bind($this->reader(), 2));

        $this->expectException(InvalidArgument::class);

        $records[3]->get('name', 2);
    }

    public function testShortRowsAndEmptyValuesAreReportedSeparately(): void
    {
        $records = $this->records(HeaderOffsetPlan::bind($this->reader(), 2));

        $withEmptyTrailingField = $records[3];
        self::assertFalse($withEmptyTrailingField->hasMissingColumns());
        self::assertSame([], $withEmptyTrailingField->missingColumns());
        self::assertTrue($withEmptyTrailingField->hasEmptyValues());
        self::assertSame(['name#1'], $this->identities($withEmptyTrailingField->emptyColumns()));
        self::assertSame([0 => 'alice', 1 => 'alice@example.net', 2 => ''], $withEmptyTrailingField->values());

        $shortRow = $records[4];
        self::assertTrue($shortRow->hasMissingColumns());
        self::assertSame(['name#1'], $this->identities($shortRow->missingColumns()));
        self::assertFalse($shortRow->hasEmptyValues());
        self::assertSame([], $shortRow->emptyColumns());
        self::assertSame([0 => 'bob', 1 => 'bob@example.net'], $shortRow->values());
        self::assertNull($shortRow->get('name', 1));
    }

    public function testPlanIsRejectedOnceTheReaderHeaderOffsetChanges(): void
    {
        $reader = $this->reader();
        $plan = HeaderOffsetPlan::bind($reader, 2);
        $this->records($plan);

        $reader->setHeaderOffset(0);

        $this->expectException(InvalidArgument::class);

        $plan->getIterator();
    }

    public function testColumnsAreRejectedOnceTheReaderHeaderOffsetChanges(): void
    {
        $reader = $this->reader();
        $plan = HeaderOffsetPlan::bind($reader, 2);

        $reader->setHeaderOffset(2);

        $this->expectException(InvalidArgument::class);

        $plan->columns();
    }

    public function testIterationReadsTheSourceExactlyOnce(): void
    {
        $plan = HeaderOffsetPlan::bind($this->reader(), 2);

        $records = $this->records($plan);

        self::assertSame([0, 1, 3, 4], array_keys($records));
        self::assertSame(5, $plan->sourceReads());
    }

    public function testHeaderResolutionDoesNotConsumeAnExtraRecord(): void
    {
        $plan = HeaderOffsetPlan::bind($this->reader(), 2);

        $plan->columns();
        self::assertSame(3, $plan->sourceReads());

        $records = $this->records($plan);

        self::assertSame([0, 1, 3, 4], array_keys($records));
        self::assertSame(['bob', 'bob@example.net'], $records[4]->raw());
        self::assertSame(8, $plan->sourceReads());
    }

    public function testTheViewDoesNotModifyTheReader(): void
    {
        $reader = $this->reader();
        $plan = HeaderOffsetPlan::bind($reader, 2);
        $this->records($plan);
        $plan->columns();

        self::assertNull($reader->getHeaderOffset());
        self::assertSame([], $reader->getHeader());
        self::assertSame([0, 1, 2, 3, 4], array_keys(iterator_to_array($reader->getRecords())));
    }

    public function testUnknownHeaderOffsetThrowsOnColumnsResolution(): void
    {
        $plan = HeaderOffsetPlan::bind($this->reader(), 42);

        $this->expectException(SyntaxError::class);

        $plan->columns();
    }

    public function testUnknownHeaderOffsetThrowsOnIteration(): void
    {
        $plan = HeaderOffsetPlan::bind($this->reader(), 42);

        $this->expectException(SyntaxError::class);

        iterator_to_array($plan->getIterator());
    }
}
