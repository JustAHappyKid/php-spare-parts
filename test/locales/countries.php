<?php

require_once 'locales/countries.php';

use \SpareParts\Locales as L;

function testIsValidCountryCode() {
  assertFalse(L\isValidCountryCode(''));
  assertFalse(L\isValidCountryCode('XX'));
  assertFalse(L\isValidCountryCode(null));
  assertTrue(L\isValidCountryCode('CH'));
  assertTrue(L\isValidCountryCode('SG'));
}
