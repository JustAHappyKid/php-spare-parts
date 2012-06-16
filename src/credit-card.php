<?php

namespace MyPHPLibs\CreditCard;

function maskCardNumber($untrimmed) {
  $cardnumber = str_replace(' ', '', $untrimmed);
  $matches = array();
  preg_match('/^([0-9]{4})([-.0-9]*)([0-9]{4})$/', $cardnumber, $matches);
  if (empty($matches)) { 
    throw new \InvalidArgumentException("Invalid credit card number given: '$untrimmed'");
  }
  $firstFour = $matches[1];
  $masked = preg_replace('/[0-9]/', '*', $matches[2]);
  $lastFour = $matches[3];
  return $firstFour . $masked . $lastFour;
}
