<?php

namespace SpareParts\ArrayLib;

use \Closure;

/**
 * This method may at first appear to be a useless wrapper for 'reset', but it serves two
 * small purposes: (1) it can be used on non-variables, and (2) it adds readability (at
 * least for those accustomed to functional programming languages).
 * 
 * Re (1): 'reset' cannot be used in a case such as this: reset(functionReturningArray())
 * Such would yield an error, as 'functionReturningArray()' is not a variable. The following
 * will work, however: head(functionReturningArray())
 */
function head(Array $a) {
  return reset($a);
}

function at($arr, $index, $default = null) {
  require_once dirname(__FILE__) . '/types.php';
  return \at($arr, $index, $default);
}

function filterByKey(Closure $qualify, Array $orig) {
  $result = array();
  foreach ($orig as $k => $v) {
    if ($qualify($k)) $result[$k] = $v;
  }
  return $result;
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

function dropWhile(Closure $qualify, Array $a) {
  $offset = 0;
  while ($qualify($a[$offset])) $offset += 1;
  return array_slice($a, $offset);
}
