<?php

require_once 'validation.php';

use \MyPHPLibs\Validation as V;

function testIsValidEmailAddr() {
  assertTrue(V\isValidEmailAddr("jeff@test.org"));
  assertFalse(V\isValidEmailAddr("jose.org"));
}

function testIsValidWebAddrAcceptsSecureURL() {
  assertTrue(V\isValidWebAddr('https://secure.downsizedc.org/contribute/'));
}

function testIsValidWebAddrAcceptsURLWithoutExplicitPath() {
  assertTrue(V\isValidWebAddr('http://www.downsizedc.org'));
}
