<?php


function get($arr, $index) {
  return isset($arr[$index]) ? $arr[$index] : null;
}


function getSubclasses($parentClassName) {
  $classes = array();
  foreach (get_declared_classes() as $className) {
    if (is_subclass_of($className, $parentClassName)) { $classes[] = $className; }
  }
  return $classes;
}


// ***********************************************************************
//
// Output all parameters passed, wrapping each in a <p> ... </p>.
// If a specified parameter is an object or array, it will be output
// using PHP's built-in var_dump() and surrounded by <pre> tags so
// output is more readable.  Any other types (string, int, ...) will be
// output with a plain-old echo().
//
// ***********************************************************************

function dump() {
  $arguments = func_get_args();
  foreach ($arguments as $this_arg) {
    echo("<p>\n");
    if (is_array($this_arg) || is_object($this_arg)) {
      echo("<pre>\n");
      var_dump($this_arg);
      echo("</pre>\n");
    } else {
      echo($this_arg);
    }
    echo("</p>\n");
  }
}


function var_repr($var, $single_line = true) {
  $exp = var_export($var, true);
  return $single_line ? str_replace("\n", " ", $exp) : $exp;
}


function path_join() {
  if (func_num_args() == 0) {
    throw new InvalidArgumentException("path_join() requires at least one parameter");
  }
  $path = "";
  for ($i = 0; $i < func_num_args(); ++$i) {
    $param = func_get_arg($i);
    if ($param == "") continue;
    if ($i != 0 and $param[0] == '/') {
      throw new InvalidArgumentException("Only the first component passed to path_join() " .
        "may be an absolute path (that is, only the first component may begin with a slash)");
    }
    $path .= $param;
    if (substr($param, -1, 1) != '/') $path .= '/';
  }
  return substr($path, 0, -1);  # Return the path minus the last slash.
}


function constructUrlFromRelativeLocation($baseUrl, $relativeLocation) {
  if (empty($baseUrl) || empty($relativeLocation)) {
    throw new InvalidArgumentException('Both parameters must be strings');
  }
  if (preg_match('/^https?:\/\//', $relativeLocation)) {
    return $relativeLocation;
  } else {
    $parts = parse_url($baseUrl);
    $path = null;
    if (substr($relativeLocation, 0, 1) == '/') {
      $path = $relativeLocation;
    } else {
      $dir = substr($parts['path'], -1) == '/' ?
        $parts['path'] : (dirname($parts['path']));
      if (substr($dir, -1) != '/') { $dir .= '/'; }
      $path = $relativeLocation == './' ? $dir : ($dir . $relativeLocation);
    }
    return $parts['scheme'] . '://' . $parts['host'] . $path;
  }
}


function getCurrentUrl() {
  if (empty($_SERVER['HTTP_HOST'])) {
    throw new Exception('HTTP_HOST not set, so cannot construct URL');
  }
  return 'http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 's' : '') .
    '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}


function redirect($relativeLocation, $secure = null) {
  $defaultUrl = constructUrlFromRelativeLocation(getCurrentUrl(), $relativeLocation);
  $url = null;
  if ($secure === null) {
    $url = $defaultUrl;
  } else {
    $url = 'http' . ($secure === true ? 's' : '') . '://' .
      ereg_replace('^http[s]*:\/\/', '', $defaultUrl);
  }
  header('Location: ' . $url);
  exit;
}
