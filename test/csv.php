<?php

require_once 'csv.php';

use \SpareParts\CSV;

function testReadingSimpleCsvContent() {

  $csvContent =
    "column1,and2\n" .
    "value1,And two.\n" .
    "last row,last value";

  $rows = CSV\toAssociativeArray($csvContent);
  assertEqual(2, count($rows));

  list($r1, $r2) = $rows;
  assertEqual('value1', $r1['column1']);
  assertEqual('And two.', $r1['and2']);
  assertEqual('last row', $r2['column1']);
  assertEqual('last value', $r2['and2']);
}

/**
 * If we attempt to render an empty array to CSV, it should be handled elegantly (either by
 * raising an `InvalidArgumentException` or by not raising any exception at all).
 */
function testHandlingOfEmptyArray() {
  try {
    CSV\fromDatabaseResult(array());
  } catch (\InvalidArgumentException $_) {
    # That's acceptable.
  }
}

// TODO: Make this pass!
/*function testReadingCsvFileThatHasNewlinesInValues() {

  $csvContent =
    "column1,\"2nd Column!\"\n" .
    "value1,line1\nAnd two.\n" .
    "\"last\nrow\",last value";

  $rows = CSV\toAssociativeArray($csvContent);
  assertEqual(2, count($rows));

  list($r1, $r2) = $rows;
  assertEqual($r1['column1'], 'value1');
  assertEqual(count(explode("\n", $r1['2nd Column!'])), 2);
  assertEqual($r2['column1'], "last\nrow");
  assertEqual($r2['2nd Column!'], "last value");
}*/
