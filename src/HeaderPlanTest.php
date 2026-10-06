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

use function array_key_exists;
use function array_key_first;
use function array_keys;
use function iterator_to_array;

/**
 * @see HeaderPlan
 * @see PlannedRecords
 *
 * Local run (from the repository root):
 *
 *     vendor/bin/phpunit src/HeaderPlanTest.php
 *
 * or, without composer installed dependencies:
 *
 *     php phpunit.phar --no-configuration --bootstrap autoload.php src/HeaderPlanTest.php
 */
#[Group('csv')]
final class HeaderPlanTest extends TestCase
{
    /**
     * Five-line document: two preamble lines, the third line is the header
     * (with a duplicated `id` column), the fourth line ends with an empty
     * field and the fifth line is a short row.
     */
    private const FIVE_LINE_CSV = <<<'CSV'
        meta,export,v1
        generated,2026-10-06
        id,name,id,note
        1,alice,100,
        2,bob
        CSV;

    private function plannedView(): array
    {
        $reader = Reader::createFromString(self::FIVE_LINE_CSV);
        $reader->setHeaderOffset(2);

        return [$reader, $reader->prepareHeaderPlan()];
    }

    public function testDataRecordNumbersKeepOriginalSourceOrderWhenThirdLineIsHeader(): void
    {
        [$reader, $plan] = $this->plannedView();

        self::assertSame(2, $plan->offset);
        self::assertSame(['id', 'name', 'id', 'note'], $plan->header);

        $records = iterator_to_array($reader->getRecordsWithPlan($plan));

        self::assertSame([3, 4], array_keys($records));
    }

    public function testDuplicateHeadersAreKeptByOccurrenceIdentity(): void
    {
        [$reader, $plan] = $this->plannedView();

        self::assertSame(['id', 'name', 'id#2', 'note'], $plan->columns);

        $records = iterator_to_array($reader->getRecordsWithPlan($plan));

        self::assertSame('1', $records[3]['id']);
        self::assertSame('100', $records[3]['id#2']);
    }

    public function testShortRowsAndEmptyTrailingFieldAreReportedSeparately(): void
    {
        [$reader, $plan] = $this->plannedView();
        $view = $reader->getRecordsWithPlan($plan);

        $records = iterator_to_array($view);

        // line 3 ends with an empty field: the column exists and is yielded as an empty string
        self::assertSame(['id' => '1', 'name' => 'alice', 'id#2' => '100', 'note' => ''], $records[3]);
        // line 4 is a short row: the missing columns are absent from the record, not null
        self::assertSame(['id' => '2', 'name' => 'bob'], $records[4]);
        self::assertFalse(array_key_exists('note', $records[4]));

        self::assertSame([4 => ['id#2', 'note']], $view->missingColumns());
        self::assertSame([3 => ['note']], $view->emptyValues());
    }

    public function testPlanBoundToAPreviousOffsetIsRejected(): void
    {
        [$reader, $plan] = $this->plannedView();

        $reader->getRecordsWithPlan($plan); // valid while the offset is unchanged
        $reader->setHeaderOffset(0); // switching the header offset invalidates the column plan

        $this->expectException(InvalidArgument::class);
        $reader->getRecordsWithPlan($plan);
    }

    public function testPlanBoundToAnotherOffsetIsRejected(): void
    {
        [$reader] = $this->plannedView();

        $stalePlan = $reader->prepareHeaderPlan(1); // explicit offset differs from the instance offset

        $this->expectException(InvalidArgument::class);
        $reader->getRecordsWithPlan($stalePlan);
    }

    public function testPlanBoundToAnotherReaderIsRejected(): void
    {
        [, $plan] = $this->plannedView();

        $other = Reader::createFromString(self::FIVE_LINE_CSV);
        $other->setHeaderOffset(2);

        $this->expectException(InvalidArgument::class);
        $other->getRecordsWithPlan($plan);
    }

    public function testIterationReadCountIsVerifiableAndPreValidationDoesNotOverConsume(): void
    {
        [$reader, $plan] = $this->plannedView(); // plan preparation pre-validates the header row
        $view = $reader->getRecordsWithPlan($plan);

        self::assertSame(0, $view->readCount());

        $records = iterator_to_array($view);

        self::assertSame(5, $view->readCount()); // exactly the five source lines were read
        self::assertSame(3, array_key_first($records)); // the first data record was not eaten by pre-validation

        iterator_to_array($view); // the view can be traversed again with a fresh iterator
        self::assertSame(5, $view->readCount());
    }

    public function testResultViewDoesNotModifyTheReader(): void
    {
        [$reader, $plan] = $this->plannedView();

        iterator_to_array($reader->getRecordsWithPlan($plan));

        self::assertSame(2, $reader->getHeaderOffset());
        self::assertSame(['id', 'name', 'id', 'note'], $reader->getHeader());
    }

    public function testLegacyGetRecordsBehaviourIsUnchanged(): void
    {
        $reader = Reader::createFromString(self::FIVE_LINE_CSV);
        $reader->setHeaderOffset(2);

        // getRecords() still rejects duplicated header column names
        $this->expectException(SyntaxError::class);
        iterator_to_array($reader->getRecords());
    }

    public function testPrepareHeaderPlanRequiresAnOffset(): void
    {
        $reader = Reader::createFromString(self::FIVE_LINE_CSV);

        $this->expectException(InvalidArgument::class);
        $reader->prepareHeaderPlan();
    }

    public function testPrepareHeaderPlanRejectsNegativeOffset(): void
    {
        $reader = Reader::createFromString(self::FIVE_LINE_CSV);

        $this->expectException(InvalidArgument::class);
        $reader->prepareHeaderPlan(-1);
    }
}
