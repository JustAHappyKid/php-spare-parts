<?php

require_once 'fs.php';

function testIsWithinDirectory() {
  assertTrue(isWithinDirectory("/path/to/a-file.txt", "/path"));
  assertTrue(isWithinDirectory("/path/to/a-file.txt", "/path/"));
  assertTrue(isWithinDirectory("/path/to/a-file.txt", "/path/to"));
  assertFalse(isWithinDirectory("/path/to/a-file.txt", "/path/to/a"));
  assertFalse(isWithinDirectory("/path/to/", "/path/to/"),
    "Directory should not be considered to be within itself");
  assertFalse(isWithinDirectory("/path/to/", "/path/to"));
  assertFalse(isWithinDirectory("/path/to", "/path/to/"));
  assertFalse(isWithinDirectory("/path/to", "/path/to"));
}
