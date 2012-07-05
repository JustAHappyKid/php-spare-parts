<?php

require_once dirname(__FILE__) . '/webapp/forms.php';
require_once dirname(__FILE__) . '/locales/us/address.php'; # getStatesMap
require_once dirname(__FILE__) . '/locales/us/forms.php';   # ZipCodeField

use \MyPHPLibs\Locales\US, \MyPHPLibs\Webapp\Forms;

function getCountriesMapForSelectField() {
  return array("" => 'Choose a country', "US" => "UNITED STATES", "CA" => "CANADA",
               "GB" => "UNITED KINGDOM", "UM" => "US MINOR OUTLYING IS.",
               "AF" => "AFGHANISTAN", "AX" => "LAND ISLANDS", "AL" => "ALBANIA",
               "DZ" => "ALGERIA", "AS" => "AMERICAN SAMOA", "AD" => "ANDORRA", "AO" => "ANGOLA",
               "AI" => "ANGUILLA", "AQ" => "ANTARCTICA", "AG" => "ANTIGUA AND BARBUDA",
               "AR" => "ARGENTINA", "AM" => "ARMENIA", "AW" => "ARUBA", "AU" => "AUSTRALIA",
               "AT" => "AUSTRIA", "AZ" => "AZERBAIJAN", "BS" => "BAHAMAS", "BH" => "BAHRAIN",
               "BD" => "BANGLADESH", "BB" => "BARBADOS", "BY" => "BELARUS", "BE" => "BELGIUM",
               "BZ" => "BELIZE", "BJ" => "BENIN", "BM" => "BERMUDA", "BT" => "BHUTAN",
               "BO" => "BOLIVIA", "BA" => "BOSNIA AND HERZEGOVINA", "BW" => "BOTSWANA",
               "BV" => "BOUVET ISLAND", "BR" => "BRAZIL", "IO" => "BRITISH INDIAN OCEAN TERRITORY",
               "BN" => "BRUNEI DARUSSALAM", "BG" => "BULGARIA", "BF" => "BURKINA FASO",
               "BI" => "BURUNDI", "KH" => "CAMBODIA", "CM" => "CAMEROON", "CV" => "CAPE VERDE",
               "KY" => "CAYMAN ISLANDS", "CF" => "CENTRAL AFRICAN REPUBLIC", "TD" => "CHAD",
               "CL" => "CHILE", "CN" => "CHINA", "CX" => "CHRISTMAS ISLAND",
               "CC" => "COCOS (KEELING) ISLANDS", "CO" => "COLOMBIA", "KM" => "COMOROS",
               "CG" => "CONGO", "CD" => "CONGO, DR", "CK" => "COOK ISLANDS", "CR" => "COSTA RICA",
               "CI" => "COTE D'IVOIRE", "HR" => "CROATIA", "CU" => "CUBA", "CY" => "CYPRUS",
               "CZ" => "CZECH REPUBLIC", "DK" => "DENMARK", "DJ" => "DJIBOUTI", "DM" => "DOMINICA",
               "DO" => "DOMINICAN REPUBLIC", "EC" => "ECUADOR", "EG" => "EGYPT",
               "SV" => "EL SALVADOR", "GQ" => "EQUATORIAL GUINEA", "ER" => "ERITREA",
               "EE" => "ESTONIA", "ET" => "ETHIOPIA", "FK" => "FALKLAND ISLANDS (MALVINAS)",
               "FO" => "FAROE ISLANDS", "FJ" => "FIJI", "FI" => "FINLAND", "FR" => "FRANCE",
               "GF" => "FRENCH GUIANA", "PF" => "FRENCH POLYNESIA",
               "TF" => "FRENCH SOUTHERN TERRITORIES", "GA" => "GABON", "GM" => "GAMBIA",
               "GE" => "GEORGIA", "DE" => "GERMANY", "GH" => "GHANA", "GI" => "GIBRALTAR",
               "GR" => "GREECE", "GL" => "GREENLAND", "GD" => "GRENADA", "GP" => "GUADELOUPE",
               "GU" => "GUAM", "GT" => "GUATEMALA", "GG" => "GUERNSEY", "GN" => "GUINEA",
               "GW" => "GUINEA-BISSAU", "GY" => "GUYANA", "HT" => "HAITI",
               "HM" => "HEARD IS. AND MCDONALD IS.", "VA" => "HOLY SEE (VATICAN CITY STATE)",
               "HN" => "HONDURAS", "HK" => "HONG KONG", "HU" => "HUNGARY", "IS" => "ICELAND",
               "IN" => "INDIA", "ID" => "INDONESIA", "IR" => "IRAN, ISLAMIC REPUBLIC OF",
               "IQ" => "IRAQ", "IE" => "IRELAND", "IM" => "ISLE OF MAN", "IL" => "ISRAEL",
               "IT" => "ITALY", "JM" => "JAMAICA", "JP" => "JAPAN", "JE" => "JERSEY",
               "JO" => "JORDAN", "KZ" => "KAZAKHSTAN", "KE" => "KENYA", "KI" => "KIRIBATI",
               "KP" => "KOREA, DPR", "KR" => "KOREA, REPUBLIC OF", "KW" => "KUWAIT",
               "KG" => "KYRGYZSTAN", "LA" => "LAO PEOPLE'S DR", "LV" => "LATVIA",
               "LB" => "LEBANON", "LS" => "LESOTHO", "LR" => "LIBERIA",
               "LY" => "LIBYAN ARAB JAMAHIRIYA", "LI" => "LIECHTENSTEIN", "LT" => "LITHUANIA",
               "LU" => "LUXEMBOURG", "MO" => "MACAO", "MK" => "MACEDONIAF", "MG" => "MADAGASCAR",
               "MW" => "MALAWI", "MY" => "MALAYSIA", "MV" => "MALDIVES", "ML" => "MALI",
               "MT" => "MALTA", "MH" => "MARSHALL ISLANDS", "MQ" => "MARTINIQUE",
               "MR" => "MAURITANIA", "MU" => "MAURITIUS", "YT" => "MAYOTTE", "MX" => "MEXICO",
               "FM" => "MICRONESIA, FEDERATED STATES OF", "MD" => "MOLDOVA, REPUBLIC OF",
               "MC" => "MONACO", "MN" => "MONGOLIA", "MS" => "MONTSERRAT", "MA" => "MOROCCO",
               "MZ" => "MOZAMBIQUE", "MM" => "MYANMAR", "NA" => "NAMIBIA", "NR" => "NAURU",
               "NP" => "NEPAL", "NL" => "NETHERLANDS", "AN" => "NETHERLANDS ANTILLES",
               "NC" => "NEW CALEDONIA", "NZ" => "NEW ZEALAND", "NI" => "NICARAGUA",
               "NE" => "NIGER", "NG" => "NIGERIA", "NU" => "NIUE", "NF" => "NORFOLK ISLAND",
               "MP" => "NORTHERN MARIANA ISLANDS", "NO" => "NORWAY", "OM" => "OMAN",
               "PK" => "PAKISTAN", "PW" => "PALAU", "PS" => "PALESTINIAN TERRITORY, OCCUPIED",
               "PA" => "PANAMA", "PG" => "PAPUA NEW GUINEA", "PY" => "PARAGUAY", "PE" => "PERU",
               "PH" => "PHILIPPINES", "PN" => "PITCAIRN", "PL" => "POLAND", "PT" => "PORTUGAL",
               "PR" => "PUERTO RICO", "QA" => "QATAR", "RE" => "REUNION", "RO" => "ROMANIA",
               "RU" => "RUSSIAN FEDERATION", "RW" => "RWANDA", "SH" => "SAINT HELENA",
               "KN" => "SAINT KITTS AND NEVIS", "LC" => "SAINT LUCIA",
               "PM" => "SAINT PIERRE AND MIQUELON", "VC" => "SAINT VINCENT AND THE GRENADINES",
               "WS" => "SAMOA", "SM" => "SAN MARINO", "ST" => "SAO TOME AND PRINCIPE",
               "SA" => "SAUDI ARABIA", "SN" => "SENEGAL", "CS" => "SERBIA AND MONTENEGRO",
               "SC" => "SEYCHELLES", "SL" => "SIERRA LEONE", "SG" => "SINGAPORE",
               "SK" => "SLOVAKIA", "SI" => "SLOVENIA", "SB" => "SOLOMON ISLANDS",
               "SO" => "SOMALIA", "ZA" => "SOUTH AFRICA", "GS" => "SOUTH GEORGIA", "ES" => "SPAIN",
               "LK" => "SRI LANKA", "SD" => "SUDAN", "SR" => "SURINAME",
               "SJ" => "SVALBARD AND JAN MAYEN", "SZ" => "SWAZILAND", "SE" => "SWEDEN",
               "CH" => "SWITZERLAND", "SY" => "SYRIAN ARAB REPUBLIC",
               "TW" => "TAIWAN, PROVINCE OF CHINA", "TJ" => "TAJIKISTAN",
               "TZ" => "TANZANIA, UNITED REPUBLIC OF", "TH" => "THAILAND", "TL" => "TIMOR-LESTE",
               "TG" => "TOGO", "TK" => "TOKELAU", "TO" => "TONGA", "TT" => "TRINIDAD AND TOBAGO",
               "TN" => "TUNISIA", "TR" => "TURKEY", "TM" => "TURKMENISTAN",
               "TC" => "TURKS AND CAICOS ISLANDS", "TV" => "TUVALU", "UG" => "UGANDA",
               "UA" => "UKRAINE", "AE" => "UNITED ARAB EMIRATES", "UY" => "URUGUAY",
               "UZ" => "UZBEKISTAN", "VU" => "VANUATU", "VE" => "VENEZUELA", "VN" => "VIET NAM",
               "VG" => "VIRGIN ISLANDS, BRITISH", "VI" => "VIRGIN ISLANDS, U.S.",
               "WF" => "WALLIS AND FUTUNA", "EH" => "WESTERN SAHARA", "YE" => "YEMEN",
               "ZM" => "ZAMBIA", "ZW" => "ZIMBABWE");
}

class CountrySelectField extends Forms\SelectField {
  function __construct($name, $label) {
    return parent::__construct($name, $label, getCountriesMapForSelectField());
  }
}
function newCountrySelectField($name, $label) {
  return new CountrySelectField($name, $label); }

class StateOrProvinceField extends Forms\BasicTextField {
  public function validateWhenNotEmpty(Array $submittedValues, $trimmedValue) {
    if ($submittedValues['country'] == 'US') {
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
function newZipOrPostalCodeField($name, $label) {
  return new ZipOrPostalCodeField($name, $label); }
