---
layout: default
title: Converting CSV delimiter
description: Convert a comma delimited CSV document into a semicolon delimited one using Reader and Writer dialect handling.
---

# CSV delimiter conversion

The `DelimiterConverter` converts a CSV document from one delimiter to another,
record by record, by relying on the existing dialect handling of the `Reader`
and the `Writer` objects: each record is parsed with the `Reader` delimiter,
enclosure and escape characters, then re-encoded with the `Writer` ones.
No string replacement is performed on the document content.

Only comma (`,`) to semicolon (`;`) conversion is supported.

## Usage

```php
use League\Csv\DelimiterConverter;
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::fromPath('/path/to/comma.csv');

$writer = Writer::fromPath('/path/to/semicolon.csv', 'w');
$writer->setDelimiter(';');

$converter = new DelimiterConverter();
$count = $converter->convert($reader, $writer);
```

The method returns the number of records converted. No header, BOM or any
other content is added to the writer: only the records yielded by the reader
are inserted.

## Example

The following comma delimited document:

```
"Smith, John","He said ""hello""",42
"line1
line2","plain",7
"a;b;c","quote: ""x""",9
```

is converted into the following semicolon delimited document:

```
"Smith, John";"He said ""hello""";42
"line1
line2";plain;7
"a;b;c";"quote: ""x""";9
```

Quoted fields, fields containing the target delimiter and fields with embedded
newlines keep their value unchanged. The converted document can be read back
using a `Reader` whose delimiter is set to `;`.
