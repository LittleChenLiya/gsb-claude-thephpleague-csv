# Explicit header offset binding plan

`League\Csv\HeaderOffsetPlan` binds a column plan to an explicitly given
header offset without touching `Reader::getRecords()` and without
modifying the `Reader`:

- original record offsets are kept as iterator keys, the real header row
  is kept apart as `HeaderOffsetColumn` instances;
- duplicate header names are preserved, each column keeps its occurrence
  identity (`name#0`, `name#1`);
- short rows (missing columns) and empty values are reported separately
  by the `HeaderOffsetRecord` views (`missingColumns()` vs `emptyColumns()`);
- the plan explicitly owns the header offset: the `Reader` must stay
  unbound. Once a header offset is set on the `Reader`, previously bound
  plans are rejected and can not be reused;
- header resolution never consumes an extra record from the returned
  iterator; the number of records read from the source is auditable via
  `HeaderOffsetPlan::sourceReads()`.

## Five-line example

`test_files/header_offset_plan.csv` — two note lines, the third line is
the header, then two data lines (an empty trailing field, then a short
row):

```csv
# quarterly export
# unverified rows
name,email,name
alice,alice@example.net,
bob,bob@example.net
```

```php
use League\Csv\HeaderOffsetPlan;
use League\Csv\Reader;

$reader = Reader::fromPath('test_files/header_offset_plan.csv');
$plan = HeaderOffsetPlan::bind($reader, 2);

$plan->columns(); // [name#0, email#0, name#1]

foreach ($plan as $offset => $record) {
    // $offset keeps the original source order: 0, 1, 3, 4
    // $record->missingColumns() vs $record->emptyColumns()
}

$plan->sourceReads(); // 5: the source was read exactly once

$reader->setHeaderOffset(0);
$plan->getIterator(); // throws InvalidArgument: the old plan can not be reused
```

## Local test command

```bash
php /d/Codex/Tools/phpunit.phar --bootstrap autoload.php --no-coverage src/HeaderOffsetPlanTest.php
```
