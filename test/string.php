<?php

require_once 'string.php';

function testContains() {
  assertTrue(contains('abc', 'abc'));
  assertTrue(contains('abc123', 'abc'));
  assertTrue(contains('456abc', 'abc'));
  assertTrue(contains('456abc', 'a'));
  assertFalse(contains('456abc', 'd'));
  assertFalse(contains('456abc', 'bcd'));
  assertFalse(contains('456abc', 'bca'));
}

function testWithoutSuffix() {
  assertEqual('index', withoutSuffix('index.php', '.php'));
  assertEqual('index.php', withoutSuffix('index.php', '.txt'));
  assertEqual('/path/to/file', withoutSuffix('/path/to/file.txt', '.txt'));
  assertEqual('/path/to/file.', withoutSuffix('/path/to/file.txt', 'txt'));
}
