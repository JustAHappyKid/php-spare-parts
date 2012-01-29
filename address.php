<?php

require_once dirname(__FILE__) . '/form.php';
require_once dirname(__FILE__) . '/us-address.php';

class StateOrProvinceInput extends TextLineInput {
  public function validateWhenNotEmpty(Array $submittedValues, $trimmedValue) {
    if ($submittedValues['country'] == 'US') {
      $states = getStatesMap($includeMilitaryPseudoStates = true, $includeTerritories = true);
      foreach ($states as $abbr => $name) {
        $upper = strtoupper($trimmedValue);
        if ($upper == $abbr || $upper == "$abbr." || $upper == strtoupper($name) ||
            $upper == strtoupper("$name ($abbr)")) {
          $this->cleanedValue = $abbr;
          return array();
        }
      }
      return array("Please provide a valid state abbreviation.");
    }
    return array();
  }
}
function newStateOrProvinceInput($name, $label) {
  return new StateOrProvinceInput($name, $label); }

class ZipOrPostalCodeInput extends ZipCodeInput {

  private $lastFourRequired = false;

  function __construct($name, $label) {
    $this->name = $name;
    $this->label = $label;
  }

  public function setDefaultValue($v) { throw new Exception('Not implemented!'); }
  public function required($_)        { throw new Exception('Not implemented!'); }
  public function addValidation($_)   { throw new Exception('Not implemented!'); }

  public function validate(Array $submittedValues) {
    $v = $this->getTrimmedValue($submittedValues);
    $this->setValue($v);
    if ($v == '' && $this->optional) {
      return array();
    } else if ($v == '' && !$this->optional) {
      // TODO: Add proper support for other countries...  Some countries do not have postal codes,
      //       but for those that do, we ought to require it (if the given instance of the field
      //       is not marked as optional).
      //       More on postal codes here: http://en.wikipedia.org/wiki/List_of_postal_codes
      if ($submittedValues['country'] == 'US') {
        return array("Please provide your ZIP Code.");
      } else {
        return array();
      }
    }
    return parent::validate($submittedValues);
  }

  public function validateWhenNotEmpty(Array $submittedValues, $trimmedValue) {
    if ($submittedValues['country'] == 'US') {
      return parent::validateWhenNotEmpty($submittedValues, $trimmedValue);
    } else {
      return array();
    }
  }
}
function newZipOrPostalCodeInput($name, $label) {
  return new ZipOrPostalCodeInput($name, $label); }
