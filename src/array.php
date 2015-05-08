<?php

namespace SpareParts\ArrayLib;

use \Closure, \InvalidArgumentException;

/**
 * This method may at first appear to be a useless wrapper for 'reset', but it serves two
 * small purposes: (1) it can be used on non-variables, and (2) it adds readability (at
 * least for those accustomed to functional programming languages).
 * 
 * Re (1): 'reset' cannot be used in a case such as this: reset(functionReturningArray());
 * Such would yield an error, as 'functionReturningArray()' is not a variable. The following
 * will work, however: head(functionReturningArray())
 *
 * TODO: Make this leave the PHP array internal pointer in tact.
 */
function head(Array $a) {
  return reset($a);
}

function tail(Array $a) {
  if (count($a) == 0)
    throw new InvalidArgumentException("Zero-length array has no tail");
  else
    return array_slice($a, 1);
}

function at($arr, $index, $default = null) {
  if ($arr === null) {
    return $default;
  } else if (!is_array($arr)) {
    throw new InvalidArgumentException("First parameter must be an array");
  } else {
    return in_array($index, array_keys($arr), $strict = true) ? $arr[$index] : $default;
  }
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

function groupBy(Closure $getKey, Array $orig) {
  $result = array();
  foreach ($orig as $a) {
    $key = $getKey($a);
    if (empty($result[$key])) $result[$key] = array();
    $result[$key] []= $a;
  }
  return $result;
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

/**
 * Return the longest-possible array that is a prefix to each sub-array (or string) of
 * the passed array, $a. For example array(array(1, 2), array(1, 2, 3, 4)) would yield
 * array(1, 2).
 */
function commonPrefix(Array $a) {

  if (func_num_args() > 1)
    throw new InvalidArgumentException("'commonPrefix' takes exactly one argument");

  $length = function($arrayOrString) {
    return is_string($arrayOrString) ? strlen($arrayOrString) : count($arrayOrString);
  };

  $slice = function($arrayOrString, $to) {
    return is_string($arrayOrString) ? substr     ($arrayOrString, 0, $to) :
                                       array_slice($arrayOrString, 0, $to);
  };

  if (count($a) == 0) {
    throw new InvalidArgumentException("Must provide at least one element");
  } else if (count($a) == 1) {
    return head($a);
  } else {
    $item1 = head($a);
    $item2 = commonPrefix(tail($a));
    $index = 0;
    while ($length($item1) >= $index + 1 &&
           $length($item2) >= $index + 1 &&
           $item1[$index] == $item2[$index])
      { ++$index; }
    return $slice($item1, $index);
  }
}
