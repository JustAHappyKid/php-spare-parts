<?php

# Convert an associated array to a string in "comma-separated values" format.
function arrayToCsv($rows, $columnsToShow) {
  $output = implode(',', $columnsToShow);
  foreach ($rows as $r) {
    $output .= "\n";
    $values = array();
    foreach ($columnsToShow as $c) $values[$c] = str_replace('"', '""', $r[$c]);
    $output .= '"' . implode('","', $values) . '"';
  }
  return $output;
}

# This function is intended for taking an associative-array result returned directly from a
# database API (PDO, specifically) and converting it to CSV content.  The thing to note, is,
# any array keys that are integers will be ignored...  For example, an array like
#   array( array('col1' => 'a', 0 => 'a', 'col2' => 'b', 1 => 'b'),
#          array('col1' => 'c', 0 => 'c', 'col2' => 'd', 1 => 'd'))
# would yield the following CSV content: "col1,col2\na,b\nc,d"
function databaseResultToCsv($dbRows) {
  $columnsToShow = array();
  foreach ($dbRows[0] as $col => $_) {
    if (!is_int($col)) $columnsToShow []= $col;
  }
  return arrayToCsv($dbRows, $columnsToShow);
}
