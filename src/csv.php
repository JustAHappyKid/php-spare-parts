<?php

namespace SpareParts\CSV;

/**
 * Convert an associative array to a string in Comma-Separated Values (CSV) format.
 */
function fromAssociativeArray($rows, $columnsToShow) {

  $makeRow = function($values) {
    $escaped = array_map(function($v) { return str_replace('"', '""', $v); }, $values);
    return '"' . implode('","', $escaped) . '"';
  };

  $output = $makeRow($columnsToShow);
  foreach ($rows as $r) {
    $output .= "\n";
    $values = array();
    foreach ($columnsToShow as $c) $values[$c] = $r[$c];
    $output .= $makeRow($values);
  }
  return $output;
}

/**
 * This function is intended for taking an associative-array result returned directly from a
 * database API (PDO, specifically) and converting it to CSV content.  The thing to note, is,
 * any array keys that are integers will be ignored...  For example, an array like
 *
 *   array( array('col1' => 'a', 0 => 'a', 'col2' => 'b', 1 => 'b'),
 *          array('col1' => 'c', 0 => 'c', 'col2' => 'd', 1 => 'd') )
 *
 * would yield CSV content equivalent to the following:
 *
 *   col1,col2
 *   a,b
 *   c,d
 */
function fromDatabaseResult(Array $dbRows) {
  if (count($dbRows) == 0)
    throw new \InvalidArgumentException("Empty array provided");
  $columnsToShow = array();
  foreach (reset($dbRows) as $col => $_) {
    if (!is_int($col)) $columnsToShow []= $col;
  }
  return fromAssociativeArray($dbRows, $columnsToShow);
}

/**
 * Convert CSV content to an associative array.
 */
// TODO: Make this work for (quoted) multi-line values
function toAssociativeArray($csvContent) {
  $rows = explode(PHP_EOL, $csvContent);
  $heading = str_getcsv(reset($rows), ',');
  return array_map(
    function($row) use($heading) { return array_combine($heading, str_getcsv($row, ',')); },
    array_slice($rows, 1));
}
