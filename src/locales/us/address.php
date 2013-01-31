<?php

namespace MyPHPLibs\Locales\US;

use \Exception, \InvalidArgumentException;

# --
# -- States
# --

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
      array("AS" => "American Samoa", "GU" => "Guam", "MP" => "Northern Mariana Islands",
            "PR" => "Puerto Rico", "VI" => "Virgin Islands") : array());
}

function getStateAbbr($stateName) {
  $map = array_flip(getStatesMap(true, true));
  if (isset($map[$stateName])) {
    return $map[$stateName];
  } else {
    throw new Exception("Could not find state named '$stateName'");
  }
}

# --
# -- ZIP codes
# --

class ZipCode {

  public $zip5, $zip4;

  public static function readFromString($zipCode) {
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
    return new ZipCode($zip5, $zip4);
  }

  function __construct($z5, $z4) {
    if (!is_string($z5) || !preg_match('/^[0-9]{5}$/', $z5)) throw new InvalidArgumentException();
    list($this->zip5, $this->zip4) = array($z5, $z4);
  }

  public function __toString() {
    return $this->zip5 . ($this->zip4 ? ('-' . $this->zip4) : '');
  }
}

class InvalidZipCode extends Exception {}
