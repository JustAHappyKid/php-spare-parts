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
