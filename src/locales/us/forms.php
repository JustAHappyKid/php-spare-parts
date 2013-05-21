<?php

namespace SpareParts\Locales\US;

require_once dirname(dirname(dirname(__FILE__))) . '/webapp/forms.php'; # Forms\...
require_once dirname(__FILE__) . '/address.php';                        # getStatesMap

use \SpareParts\Webapp\Forms;

# --
# -- States
# --

function newStateSelectField($name, $label, $includeMilitaryPseudoStates = true,
                             $includeTerritories = true) {
  return Forms\newSelectField($name, $label,
    getStatesMapForSelectField($includeMilitaryPseudoStates, $includeTerritories));
}

function getStatesMapForSelectField($includeMilitaryPseudoStates = true,
                                    $includeTerritories = true) {
  $states = array('' => 'Choose a state');
  foreach (getStatesMap($includeMilitaryPseudoStates, $includeTerritories) as $abbr => $name) {
    $states[$abbr] = $abbr . ' - ' . $name;
  }
  return $states;
}

# --
# -- ZIP codes
# --

class NineDigitZipCodeField extends Forms\BasicTextField {

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

function newNineDigitZipCodeField($name, $label, $lastFourRequired) {
  return new NineDigitZipCodeField($name, $label, $lastFourRequired);
}

class ZipCodeField extends Forms\BasicTextField {

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
      // list($z5, $z4) = readZipCode($trimmedValue);
      $zip = ZipCode::readFromString($trimmedValue);
      if ($this->lastFourRequired && empty($zip->zip4)) {
        return array('Please provide the last four digits of your ZIP code.');
      } else {
        return array();
      }
    } catch (InvalidZipCode $_) {
      return array("Please provide a valid ZIP code.");
    }
  }
}

function newZipCodeField($name, $label, $lastFourRequired) {
  return new ZipCodeField($name, $label, $lastFourRequired);
}
