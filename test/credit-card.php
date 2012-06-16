<?php

require_once 'credit-card.php';
use \MyPHPLibs\CreditCard;

function testMaskingCreditCardNumber() {
  assertEqual('1234-****-****-4321', CreditCard\maskCardNumber('1234-5678-8765-4321'));
  assertEqual('1111********2222', CreditCard\maskCardNumber('1111555599992222'));
  assertEqual('1111********2222', CreditCard\maskCardNumber('1111 5555 9999 2222'));
  assertEqual('1234*4321', CreditCard\maskCardNumber('123454321'));
}

function testMaskCreditCardNumberGracefullyHandlesInvalidCardNumber() {
  $badValues = array('4301', '3422$1798z4844#7732', '123412');
  foreach ($badValues as $v) {
    try {
      CreditCard\maskCardNumber($v);
      fail("Expected 'maskCardNumber' to raise exception for value '$v'");
    } catch (InvalidArgumentException $e) {
      // That's expected...
    }
  }
}
