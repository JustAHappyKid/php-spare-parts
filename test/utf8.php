<?php

require_once 'utf8.php';

function testHasInvalidUTF8Chars() {
  assertTrue(hasInvalidUTF8Chars("String with invalid \x93characters\x94 in it"));
  assertFalse(hasInvalidUTF8Chars("String without invalid characters in it"));
}

function testPurgeInvalidUTF8Chars() {
  assertEqual("String with invalid characters",
    purgeInvalidUTF8Chars("String with invalid \x93characters\x94"));
}
