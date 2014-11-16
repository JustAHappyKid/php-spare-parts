<?php

require_once 'config.php';

use \SpareParts\Config;

function testBasicConfigParsing() {
  $vs = Config\parse("a=b\n" . "var2 = 372");
  assertEqual("b", $vs['a']);
  assertEqual("372", $vs['var2']);
}

function testSupportForCaseAndValuesWithSpaces() {
  $vs = Config\parse("myVar = A sentence of text.");
  assertEqual("A sentence of text.", $vs['myVar']);
}

function testBlankLinesAreIgnored() {
  $vs = Config\parse("\n\na=b\nc=d\n\ne=f\n");
  assertEqual(3, count($vs));
  assertEqual("d", $vs['c']);
}

function testSupportForComments() {
  $vs = Config\parse(
    "# This is line 1.\n" .
    "someVar = Some Value\n" .
    "# More comments here\n" .
    "# and here.\n" .
    "other = ok");
  assertEqual(2, count($vs));
}
