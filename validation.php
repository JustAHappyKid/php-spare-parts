<?php

namespace MyPHPLibs\Validation;

# If $allowExtendedFormat is true, then a value like "Jimmy Petersen <jp@example.org>" will
# be accepted.
function isValidEmailAddr($input, $allowExtendedFormat = false) {
  $email = $input;
  $matches = null;
  if ($allowExtendedFormat && preg_match('/^[^<>]+\s*<([^<>]+)>$/', $input, $matches)) {
    $email = $matches[1];
  }
  return EmailAddressValidator::checkEmailAddress($email);
}

function isValidWebAddr($a) {
  $parts = parse_url($a);
  return !($parts == false || empty($parts['scheme']) || empty($parts['host']));
}

class EmailAddressValidator {

  # Check whether given string is in the form of a valid email address,
  # returning true if it is, or false otherwise.
  public static function checkEmailAddress($strEmailAddress) {

    // Control characters are not allowed
    if (preg_match('/[\x00-\x1F\x7F-\xFF]/', $strEmailAddress)) {
        return false;
    }

    # Check email length - min 3 (a@a), max 256
    if (!self::checkTextLength($strEmailAddress, 3, 256)) {
        return false;
    }

    # Split it into sections using last instance of "@"
    $intAtSymbol = strrpos($strEmailAddress, '@');
    if ($intAtSymbol === false) {
      return false; # No "@" symbol in email.
    }
    $arrEmailAddress[0] = substr($strEmailAddress, 0, $intAtSymbol);
    $arrEmailAddress[1] = substr($strEmailAddress, $intAtSymbol + 1);

    # Count the "@" symbols. Only one is allowed, except where 
    # contained in quote marks in the local part. Quickest way to
    # check this is to remove anything in quotes. We also remove
    # characters escaped with backslash, and the backslash character.
    $arrTempAddress[0] = preg_replace('/\./', '', $arrEmailAddress[0]);
    $arrTempAddress[0] = preg_replace('/"[^"]+"/', '', $arrTempAddress[0]);
    $arrTempAddress[1] = $arrEmailAddress[1];
    $strTempAddress = $arrTempAddress[0] . $arrTempAddress[1];
    # Then check - should be no "@" symbols.
    if (strrpos($strTempAddress, '@') !== false) {
      return false; # "@" symbol found
    }

    # Check local portion
    if (!self::checkLocalPortion($arrEmailAddress[0])) {
      return false;
    }

    # Check domain portion
    if (!self::checkDomainPortion($arrEmailAddress[1])) {
      return false;
    }

    # If we're still here, all checks above passed. Email is valid.
    return true;
  }

  # Checks email section before "@" symbol for validity
  protected static function checkLocalPortion($strLocalPortion) {
    # Local portion can only be from 1 to 64 characters, inclusive.
    # Please note that servers are encouraged to accept longer local
    # parts than 64 characters.
    if (!self::checkTextLength($strLocalPortion, 1, 64)) {
      return false;
    }
    # Local portion must be:
    # 1) a dot-atom (strings separated by periods)
    # 2) a quoted string
    # 3) an obsolete format string (combination of the above)
    $arrLocalPortion = explode('.', $strLocalPortion);
    for ($i = 0, $max = sizeof($arrLocalPortion); $i < $max; $i++) {
      if (!preg_match('.^('
                    .    '([A-Za-z0-9!#$%&\'*+/=?^_`{|}~-]' 
                    .    '[A-Za-z0-9!#$%&\'*+/=?^_`{|}~-]{0,63})'
                    .'|'
                    .    '("[^\\\"]{0,62}")'
                    .')$.'
                    ,$arrLocalPortion[$i])) {
        return false;
      }
    }
    return true;
  }

  # Checks email section after "@" symbol for validity.
  protected static function checkDomainPortion($strDomainPortion) {
    # Total domain can only be from 1 to 255 characters, inclusive
    if (!self::checkTextLength($strDomainPortion, 1, 255)) {
      return false;
    }
    // Check if domain is IP, possibly enclosed in square brackets.
    if (preg_match('/^(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])'
                  .'(\.(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])){3}$/'
                  ,$strDomainPortion) || 
        preg_match('/^\[(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])'
                   .'(\.(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])){3}\]$/'
                   ,$strDomainPortion)) {
      return true;
    } else {
      $arrDomainPortion = explode('.', $strDomainPortion);
      if (sizeof($arrDomainPortion) < 2) {
          return false; # Not enough parts to domain
      }
      for ($i = 0, $max = sizeof($arrDomainPortion); $i < $max; $i++) {
        # Each portion must be between 1 and 63 characters, inclusive
        if (!self::checkTextLength($arrDomainPortion[$i], 1, 63)) {
          return false;
        }
        if (!preg_match('/^(([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|'
           .'([A-Za-z0-9]+))$/', $arrDomainPortion[$i])) {
          return false;
        }
        if ($i == $max - 1) { # TLD cannot be only numbers
          if (strlen(preg_replace('/[0-9]/', '', $arrDomainPortion[$i])) <= 0) {
            return false;
          }
        }
      }
    }
    return true;
  }

  # Return true if string is within bounds (inclusive), false if not.
  protected static function checkTextLength($strText, $intMinimum, $intMaximum) {
    $intTextLength = strlen($strText);
    if (($intTextLength < $intMinimum) || ($intTextLength > $intMaximum)) {
      return false;
    } else {
      return true;
    }
  }
}
