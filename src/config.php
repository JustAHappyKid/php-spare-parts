<?php

/**
 * Support for reading simple name/value-pair configuration files.
 */

namespace SpareParts\Config;

require_once dirname(__FILE__) . '/string.php';  # beginsWith, contains

function fromFile($pathToFile) {
  return parse(file_get_contents($pathToFile));
}

function parse($content) {
  $lines = explode("\n", $content);
  $relevantLines = array_filter($lines,
    function($l) { return !beginsWith($l, '#') && trim($l) != ''; });
  $values = array();
  foreach ($relevantLines as $l) {
    if (!contains($l, '=')) {
      throw new Exception("Invalid line found in config file; no equals sign found: $l");
    }
    list($name, $value) = explode('=', $l, 2);
    $values[trim($name)] = trim($value);
  }
  return $values;
}
