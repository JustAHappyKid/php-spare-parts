<?php

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
