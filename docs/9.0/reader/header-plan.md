---
layout: default
title: Explicit header offset binding plan
description: Bind a column plan to an explicit header offset without modifying the Reader.
---

# Header offset binding plan

The `Reader::prepareHeaderPlan` method captures, at creation time, the header offset and the real
header row of the document into an immutable `League\Csv\HeaderPlan`. The `Reader::getRecordsWithPlan`
method then returns a lazy `League\Csv\PlannedRecords` view over the data records.

- Original record offsets (the source sequence numbers) are kept separate from the real header row:
  the header lives in the plan and records at or before the header offset are never yielded as data;
  data records keep their original source offsets as keys.
- The plan is bound to the `Reader` instance and to its current header offset. Switching the header
  offset invalidates the plan: the old column plan can not be reused and is rejected with an
  `InvalidArgument` exception. Prepare a new plan instead.
- Duplicated header names are kept by occurrence identity: the first occurrence keeps the bare name,
  subsequent ones are suffixed with their occurrence number (`id`, `id#2`, `id#3`, ...).
- Short rows (missing columns) and empty values are reported separately via
  `PlannedRecords::missingColumns()` and `PlannedRecords::emptyValues()`; missing columns are absent
  from the yielded record while empty values are yielded as-is.
- The plan pre-validation never consumes an extra record from the iterator; the number of source
  records read during a traversal is exposed via `PlannedRecords::readCount()`.
- The returned view never modifies the `Reader` state, and `Reader::getRecords` is left unchanged.

## Five-line example

Given the following five-line document where the first two lines are preamble lines and the third
line is the header:

```csv
meta,export,v1
generated,2026-10-06
id,name,id,note
1,alice,100,
2,bob
```

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$reader->setHeaderOffset(2); // the third line is the header

$plan = $reader->prepareHeaderPlan();
$plan->header;  // ['id', 'name', 'id', 'note'] the real header row
$plan->columns; // ['id', 'name', 'id#2', 'note'] duplicates kept by occurrence identity

$view = $reader->getRecordsWithPlan($plan);
$records = iterator_to_array($view);

array_keys($records); // [3, 4] data numbers keep the original source order
$records[3];          // ['id' => '1', 'name' => 'alice', 'id#2' => '100', 'note' => '']
$records[4];          // ['id' => '2', 'name' => 'bob'] missing columns are absent

$view->missingColumns(); // [4 => ['id#2', 'note']] the short row
$view->emptyValues();    // [3 => ['note']] the empty trailing field
$view->readCount();      // 5 source records were read, no extra read from pre-validation

$reader->setHeaderOffset(0);
$reader->getRecordsWithPlan($plan); // throws InvalidArgument: the old offset plan is rejected
```

## Running the tests locally

The feature is covered by a dedicated test file which can be run on its own from the repository
root:

```bash
vendor/bin/phpunit src/HeaderPlanTest.php
```

or, without composer installed dependencies, using a PHPUnit PHAR and the repository autoloader:

```bash
php phpunit.phar --no-configuration --bootstrap autoload.php src/HeaderPlanTest.php
```
