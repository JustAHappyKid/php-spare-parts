<?php

require_once dirname(__FILE__) . '/array.php';

use \SpareParts\ArrayLib as A;

function contains($haystack, $needle) {
  return strpos($haystack, $needle) !== false;
}

# Returns true if $str begins with $sub.
function beginsWith($str, $sub) {
  return substr($str, 0, strlen($sub)) == $sub;
}

# Return true if $str ends with $sub.
function endsWith($str, $sub) {
  return substr($str, strlen($str) - strlen($sub)) == $sub;
}

function withoutSuffix($str, $suffix) {
  return substr($str, 0 - strlen($suffix)) == $suffix ?
    substr($str, 0, 0 - strlen($suffix)) : $str;
}

function withoutPrefix($str, $prefix, $caseInsensitive = true) {
  if (substr($caseInsensitive ? strtolower($str) : $str, 0, strlen($prefix)) ==
      ($caseInsensitive ? strtolower($prefix) : $prefix)) {
    return substr($str, strlen($prefix));
  } else {
    return $str;
  }
}

/**
 * Return the longest-possible string that is a prefix to every string in the given
 * array (assuming all elements of the array are strings).
 */
function commonPrefix(Array $strings) {
  if (count($strings) == 0) {
    throw new InvalidArgumentException("Must provide at least one element");
  } else if (count($strings) == 1) {
    $item = A\head($strings);
    if (!is_string($item))
      throw new InvalidArgumentException("All elements of given array must be strings");
    return $item;
  } else {
    $s1 = A\head($strings);
    $s2 = commonPrefix(A\tail($strings));
    $i = 0;
    while (strlen($s1) >= $i + 1 && strlen($s2) >= $i + 1 && $s1[$i] == $s2[$i]) ++$i;
    return substr($s1, 0, $i);
  }
}
