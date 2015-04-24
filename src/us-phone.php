<?php

namespace SpareParts\Locales\US;

require_once dirname(__FILE__) . '/webapp/forms.php';
use \SpareParts\Webapp\Forms\BasicTextField;

class InvalidPhoneNumber extends \InvalidArgumentException {}

class PhoneNumber {

  private $areaCode, $local7;

  function __construct($ac, $l7) {
    $this->areaCode = $ac;
    $this->local7 = $l7;
  }

  public static function fromText($pn) {
    $patterns = array(
      '(1[-.\s])?([0-9]{3})[-.\s]([0-9]{3})[-.]([0-9]{4})',
      '(1[-.\s])?([0-9]{3})[-.\s]([0-9]{3})([0-9]{4})',
      '(1\s*)?([0-9]{3})\s*([0-9]{3})\s*([0-9]{4})',
      '(1)?([0-9]{3})\s?([0-9]{3})\s?([0-9]{4})',
      '(1)?\s*\(([0-9]{3})\)\s*\-?\s*([0-9]{3})[-.\s]?([0-9]{4})',
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

class PhoneNumberField extends BasicTextField {
  public function validateWhenNotEmpty(Array $submittedValues, $trimmedValue) {
    try {
      $pn = PhoneNumber::fromText($trimmedValue);
      $this->cleanedValue = $pn->asRawTenDigits();
      return array();
    } catch (InvalidPhoneNumber $_) {
      return array('Please provide a 10-digit phone number (e.g., 123-456-7890).');
    }
  }
}

function newPhoneNumberField($name, $label) {
  return new PhoneNumberField($name, $label);
}
