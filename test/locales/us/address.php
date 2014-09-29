<?php

require_once 'locales/us/address.php';
use \SpareParts\Locales\US;

function testGetStateAbbrIsCaseInsensitive() {
  assertEqual('AZ', US\getStateAbbr('Arizona'));
  assertEqual('OH', US\getStateAbbr('ohio'));
  assertEqual('NJ', US\getStateAbbr('New jersey'));
  assertEqual('GA', US\getStateAbbr('GEORGIA'));
}

function testIsValidStateAbbr() {
  assertTrue(US\isValidStateAbbr('ME'));
  assertFalse(US\isValidStateAbbr('XX'));
}

function testIsValidStateName() {
  assertTrue(US\isValidStateName('California'));
  assertFalse(US\isValidStateName('Johnson'));
}
