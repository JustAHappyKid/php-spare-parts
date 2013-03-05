<?php

namespace MyPHPLibs\ArrayLib;

/**
 * This method may at first appear to be a useless wrapper for 'reset', but it serves two
 * little purpsoses: (1) it can be used on non-variables, and (2) it adds readability (at
 * least for those accustomed to functional programming languages).
 * 
 * Re (1): 'reset' cannot be used in a case such as this: reset(functionReturningArray())
 * Such would yield an error, as 'functionReturningArray()' is not a variable.
 */
function head(Array $a) {
  return reset($a);
}

function flatten(Array $origArray) {
  $flatArray = array();
  foreach ($origArray as $a) $flatArray = array_merge($flatArray, $a);
  return $flatArray;
}
