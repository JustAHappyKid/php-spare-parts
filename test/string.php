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

function testCommonPrefix() {
  assertEqual("hello", commonPrefix(array("hello")));
  assertEqual("h", commonPrefix(array("hello", "hi")));
  assertEqual("/home/johnny/appleseeds/",
    commonPrefix(array(
      "/home/johnny/appleseeds/red/pink-lady",
      "/home/johnny/appleseeds/red/delicious",
      "/home/johnny/appleseeds/green/green-apple")));
  assertThrows('InvalidArgumentException', function() { commonPrefix(array()); });
}
