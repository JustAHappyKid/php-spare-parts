<?php

require_once 'names.php';
use \SpareParts\Names;

function testUnderscoreToCamelCase() {
  assertEqual('littleAlexHi', Names\underscoreToCamelCase('little_alex_hi'));
}

function testCamelCaseToUnderscore() {
  assertEqual('where_is_the_bathroom', Names\camelCaseToUnderscore('whereIsTheBathroom'));
}

function testHyphenatedToCamelCase() {
  assertEqual('holaBuenosDias', Names\hyphenatedToCamelCase('hola-buenos-dias'));
}

function testCamelCaseToHyphenated() {
  assertEqual('no-camels-here', Names\camelCaseToHyphenated('noCamelsHere'));
}
