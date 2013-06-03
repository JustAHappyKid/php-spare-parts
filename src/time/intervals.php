<?php

namespace SpareParts\Time;

use \InvalidArgumentException, \DateInterval;

# TODO: Extend this function to support other time units -- years, months, minutes, seconds.
function readInterval($s) {
  $parts = explode(' ', $s);
  if (count($parts) != 2 || !is_numeric($parts[0])) {
    throw new InvalidArgumentException("Parameter must be in format '[number] [unit-of-time]'");
  }
  $unit = strtolower($parts[1]);
  $bigUnits = array('day' => 'D', 'days' => 'D');
  $smallUnits = array('hour' => 'H', 'hours' => 'H');
  if (isset($bigUnits[$unit])) {
    return new DateInterval('P' . $parts[0] . $bigUnits[$unit]);
  } else if (isset($smallUnits[$unit])) {
    return new DateInterval('PT' . $parts[0] . $smallUnits[$unit]);
  } else {
    throw new InvalidArgumentException("Unrecognized time unit specified: $unit");
  }
}
