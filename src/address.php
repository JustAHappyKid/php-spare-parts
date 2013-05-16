<?php

require_once dirname(__FILE__) . '/webapp/forms.php';
require_once dirname(__FILE__) . '/locales/countries.php';  # countriesMap
require_once dirname(__FILE__) . '/locales/us/address.php'; # getStatesMap
require_once dirname(__FILE__) . '/locales/us/forms.php';   # ZipCodeField

use \MyPHPLibs\Locales, \MyPHPLibs\Locales\US, \MyPHPLibs\Webapp\Forms;

function getCountriesMapForSelectField() {
  return array_merge(array("" => 'Choose a country'), Locales\countriesMap());
}

class CountrySelectField extends Forms\SelectField {
  function __construct($name, $label) {
    return parent::__construct($name, $label, getCountriesMapForSelectField());
  }
}
function newCountrySelectField($name, $label) {
  return new CountrySelectField($name, $label); }

class StateOrProvinceField extends Forms\BasicTextField {

  private $countryField;
  public function setAssociatedCountryField($name) { $this->countryField = $name; return $this; }

  public function validateWhenNotEmpty(Array $submittedValues, $trimmedValue) {
    if ($this->countryField && $submittedValues[$this->countryField] == 'US') {
      $states = US\getStatesMap($includeMilitaryPseudoStates = true, $includeTerritories = true);
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
function newStateOrProvinceField($name, $label) {
  return new StateOrProvinceField($name, $label); }

class ZipOrPostalCodeField extends US\ZipCodeField {

  private $lastFourRequired = false;

  function __construct($name, $label) {
    $this->name = $name;
    $this->label = $label;
  }

  public function setDefaultValue($v1, $v2) { throw new Exception('Not implemented!'); }
  public function required($_)              { throw new Exception('Not implemented!'); }
  public function addValidation($_)         { throw new Exception('Not implemented!'); }

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
function newZipOrPostalCodeField($name, $label) {
  return new ZipOrPostalCodeField($name, $label); }
