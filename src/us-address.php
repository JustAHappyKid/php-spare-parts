<?php

require_once dirname(__FILE__) . '/form.php';

class ZipCode {

  public $zip5, $zip4;

  function __construct($z5, $z4) {
    if (!is_string($z5) || !preg_match('/^[0-9]{5}$/', $z5)) throw new InvalidArgumentException();
    list($this->zip5, $this->zip4) = array($z5, $z4);
  }

  public function __toString() {
    return $this->zip5 . ($this->zip4 ? ('-' . $this->zip4) : '');
  }
}

function getStateAbbr($stateName) {
  $map = array_flip(getStatesMap(true, true));
  if (isset($map[$stateName])) {
    return $map[$stateName];
  } else {
    throw new Exception("Could not find state named '$stateName'");
  }
}

function getStatesMap($includeMilitaryPseudoStates, $includeTerritories) {
  return array_merge(
    $includeMilitaryPseudoStates ?
      array("AA" => "Armed Forces, Americas", "AE" => "Armed Forces, Europe and et al", 
            "AP" => "Armed Forces, Pacific") : array(),
      array("AL" => "Alabama", "AK" => "Alaska", "AZ" => "Arizona", "AR" => "Arkansas",
            "CA" => "California", "CO" => "Colorado", "CT" => "Connecticut",
            "DE" => "Delaware", "DC" => "District of Columbia", "FL" => "Florida",
            "GA" => "Georgia", "HI" => "Hawaii", "ID" => "Idaho", "IL" => "Illinois",
            "IN" => "Indiana", "IA" => "Iowa", "KS" => "Kansas", "KY" => "Kentucky",
            "LA" => "Louisiana", "ME" => "Maine", "MD" => "Maryland",
            "MA" => "Massachusetts", "MI" => "Michigan", "MN" => "Minnesota",
            "MS" => "Mississippi", "MO" => "Missouri", "MT" => "Montana", "NE" => "Nebraska",
            "NV" => "Nevada", "NH" => "New Hampshire", "NJ" => "New Jersey",
            "NM" => "New Mexico", "NY" => "New York", "NC" => "North Carolina",
            "ND" => "North Dakota", "OH" => "Ohio", "OK" => "Oklahoma", "OR" => "Oregon",
            "PA" => "Pennsylvania", "RI" => "Rhode Island", "SC" => "South Carolina",
            "SD" => "South Dakota", "TN" => "Tennessee", "TX" => "Texas", "UT" => "Utah",
            "VT" => "Vermont", "VA" => "Virginia", "WA" => "Washington",
            "WV" => "West Virginia", "WI" => "Wisconsin", "WY" => "Wyoming"),
    $includeTerritories ?
      array("AS" => "American Samoa", "GU" => "Guam", "PR" => "Puerto Rico",
            "VI" => "Virgin Islands") : array());
}

function getStatesMapForSelectField($includeMilitaryPseudoStates = true,
                                    $includeTerritories = true) {
  $states = array('' => 'Choose a state');
  foreach (getStatesMap($includeMilitaryPseudoStates, $includeTerritories) as $abbr => $name) {
    $states[$abbr] = $abbr . ' - ' . $name;
  }
  return $states;
}

function readZipCode($zipCode) {
  $zip5 = null; $zip4 = null;
  if (preg_match('/^[0-9]{5}$/', $zipCode)) {
    $zip5 = $zipCode;
  } else if (preg_match('/^[0-9]{5}\-[0-9]{4}$/', $zipCode)) {
    list($zip5, $zip4) = explode('-', $zipCode);
  } else if (preg_match('/^[0-9]{9}$/', $zipCode)) {
    $zip5 = substr($zipCode, 0, 5);
    $zip4 = substr($zipCode, 5, 4);
  } else {
    throw new InvalidZipCode("Invaild ZIP code provided: $zipCode");
  }
  return array($zip5, $zip4);
}

class NineDigitZipCodeInput extends TextLineInput {

  private $lastFourRequired;
  public $zip5 = '', $zip4 = '';

  function __construct($name, $label, $lastFourRequired) {
    $this->name = $name;
    $this->label = $label;
    $this->lastFourRequired = $lastFourRequired;
    //$this->requiredErr = "Please provide a value for the field <em>$label</em>";
  }

  public function setDefaultValue($zip5, $zip4) {
    $this->zip5 = $zip5; $this->zip4 = $zip4;
    return $this;
  }

  public function validate(Array $submittedValues) {
    $z5 = $this->getZip5($submittedValues); $z4 = $this->getZip4($submittedValues);
    if ($this->optional && $z5 == '' && $z4 == '') {
      $this->cleanedValue = '';
      return array();
    } else {
      if (!preg_match('/^[0-9]{5}$/', $z5)) {
        return array("Please provide exactly five digits for the first portion of your ZIP code.");
      } else if (($z4 || $this->lastFourRequired) && !preg_match('/^[0-9]{4}$/', $z4)) {
        return array("Please provide exactly four digits for the second portion of your ZIP code.");
      } else {
        $this->cleanedValue = $this->getTrimmedValue($submittedValues);
        $this->zip5 = $this->getZip5($submittedValues);
        $this->zip4 = $this->getZip4($submittedValues);
        return array();
      }
    }
  }

  protected function getTrimmedValue(Array $submittedValues) {
    return $this->getZip5($submittedValues) . '-' . $this->getZip4($submittedValues);
  }

  private function getZip5(Array $submittedValues) {
    return trim(at($submittedValues, $this->name . '-zip5', ''));
  }

  private function getZip4(Array $submittedValues) {
    return trim(at($submittedValues, $this->name . '-zip4', ''));
  }

  public function renderInputHtml() {
    $this->attributes['type'] = 'text';
    return $this->renderInputField($this->name . '-zip5', $this->zip5, 'zip-code') . ' - ' .
      $this->renderInputField($this->name . '-zip4', $this->zip4, 'zip-code-last-4');
  }

  private function renderInputField($name, $value, $cls) {
    return '<input type="text" name="' . $name . '" value="' . htmlspecialchars($value) .
      '" class="' . $cls . '" />';
  }
}

function newNineDigitZipCodeInput($name, $label, $lastFourRequired) {
  return new NineDigitZipCodeInput($name, $label, $lastFourRequired);
}

class ZipCodeInput extends TextLineInput {

  private $lastFourRequired;
  //public $zip5 = '', $zip4 = '';

  function __construct($name, $label, $lastFourRequired) {
    $this->name = $name;
    $this->label = $label;
    $this->lastFourRequired = $lastFourRequired;
  }

  public function setDefaultValue($zip5, $zip4) {
    $this->zip5 = $zip5; $this->zip4 = $zip4;
    return $this;
  }

  public function validateWhenNotEmpty(Array $submittedValues, $trimmedValue) {
    try {
      list($z5, $z4) = readZipCode($trimmedValue);
      if ($this->lastFourRequired && empty($z4)) {
        return array('Please provide the last four digits of your ZIP code.');
      } else {
        return array();
      }
    } catch (InvalidZipCode $_) {
      return array("Please provide a valid ZIP code.");
    }
  }
}

function newZipCodeInput($name, $label, $lastFourRequired) {
  return new ZipCodeInput($name, $label, $lastFourRequired);
}
