<?php

namespace MyPHPLibs\Locales\US;

require_once dirname(__FILE__) . '/form.php';
use \TextLineInput;

class InvalidPhoneNumber extends \InvalidArgumentException {}

class PhoneNumber {

  private $areaCode, $local7;

  function __construct($ac, $l7) {
    $this->areaCode = $ac;
    $this->local7 = $l7;
  }

  public static function fromText($pn) {
    $patterns = array(
      '(1[.-\s])?([0-9]{3})[.-\s]([0-9]{3})[.-]([0-9]{4})',
      '(1[.-\s])?([0-9]{3})[.-\s]([0-9]{3})([0-9]{4})',
      '(1\s*)?([0-9]{3})\s*([0-9]{3})\s*([0-9]{4})',
      '(1)?([0-9]{3})\s?([0-9]{3})\s?([0-9]{4})',
      '(1)?\s*\(([0-9]{3})\)\s*\-?\s*([0-9]{3})[.-\s]?([0-9]{4})',
      '(1\s*\/)?\s*([0-9]{3})\s*\/\s*([0-9]{3})\s*\/\s*([0-9]{4})',
      '(1)?\s*([0-9]{3})\s*\/\s*([0-9]{3})\s*-\s*([0-9]{4})');
    foreach ($patterns as $pattern) {
      $m = null;
      if (preg_match("/^$pattern$/", $pn, $m)) {
        return new PhoneNumber($m[2], $m[3] . $m[4]);
      }
    }
    throw new InvalidPhoneNumber("Invalid phone number provided: $pn");
  }

  public function asRawTenDigits() {
    return $this->areaCode . $this->local7;
  }
}

class PhoneNumberInput extends TextLineInput {
  public function validateWhenNotEmpty(Array $submittedValues, $trimmedValue) {
    try {
      $pn = PhoneNumber::fromText($trimmedValue);
      $this->cleanedValue = $pn->asRawTenDigits();
      return array();
    } catch (InvalidPhoneNumber $_) {
      notice('The following invalid phone number was provided: ' . $submittedValues[$this->name]);
      return array('Please provide a 10-digit phone number (e.g., 123-456-7890).');
    }
  }
}

function newPhoneNumberInput($name, $label) {
  return new PhoneNumberInput($name, $label);
}
