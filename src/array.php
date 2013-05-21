<?php

namespace SpareParts\ArrayLib;

use \Closure;

/**
 * This method may at first appear to be a useless wrapper for 'reset', but it serves two
 * little purpsoses: (1) it can be used on non-variables, and (2) it adds readability (at
 * least for those accustomed to functional programming languages).
 * 
 * Re (1): 'reset' cannot be used in a case such as this: reset(functionReturningArray())
 * Such would yield an error, as 'functionReturningArray()' is not a variable. The following
 * will work, however: head(functionReturningArray())
 */
function head(Array $a) {
  return reset($a);
}

function flatten(Array $origArray) {
  $flatArray = array();
  foreach ($origArray as $a) $flatArray = array_merge($flatArray, $a);
  return $flatArray;
}

function takeWhile(Closure $qualify, Array $a) {
  $result = array();
  foreach ($a as $item) {
    if ($qualify($item)) {
      $result []= $item;
    } else {
      break;
    }
  }
  return $result;
}
