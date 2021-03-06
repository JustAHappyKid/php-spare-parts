<?php

namespace SpareParts\URL;

require_once dirname(__FILE__) . '/file-path.php';

use \InvalidArgumentException, \SpareParts\FilePath;

function takeScheme($url) { return takeUrlPart($url, 'scheme'); }
function takeDomain($url) { return takeUrlPart($url, 'host');   }
function takePath  ($url) { return takeUrlPart($url, 'path');   }
function takeQuery ($url) { return takeUrlPart($url, 'query');  }

function takePathAndQuery($url) {
  $q = takeQuery($url);
  return takePath($url) . ($q ? ('?' . $q) : '');
}

function takeUrlPart($url, $part) {
  $parts = parse_url($url);
  if (empty($parts['scheme']) || empty($parts['host'])) {
    throw new InvalidArgumentException("Invalid URL: $url");
  }
  return isset($parts[$part]) ? $parts[$part] : null;
}

function constructUrlFromRelativeLocation($baseUrl, $relativeLocation, $secure = null) {
  if (empty($baseUrl) || empty($relativeLocation)) {
    throw new InvalidArgumentException('Both parameters must be strings');
  }
  if (preg_match('/^https?:\/\//', $relativeLocation)) {
    return $relativeLocation;
  } else {
    $parts = parse_url($baseUrl);
    if (empty($parts['scheme'])) {
      throw new InvalidArgumentException('First parameter must be an absolute URL');
    }
    $path = null;
    if (substr($relativeLocation, 0, 1) == '/') {
      $path = $relativeLocation;
    } else {
      if (empty($parts['path'])) $parts['path'] = '/';
      $dir = substr($parts['path'], -1) == '/' ?
        $parts['path'] : (dirname($parts['path']));
      if (substr($dir, -1) != '/') { $dir .= '/'; }
      //$path = $relativeLocation == './' ? $dir : ($dir . $relativeLocation);
      $normalizedPath = FilePath\normalize($dir . $relativeLocation);
      $path = $normalizedPath . (substr($relativeLocation, -1) == '/' ? '/' : '');
    }
    $scheme = null;
    if ($secure === null) $scheme = $parts['scheme'];
    else if ($secure === true) $scheme = 'https';
    else if ($secure === false) $scheme = 'http';
    return $scheme . '://' . $parts['host'] . $path;
  }
}

# XXX: 'makeUrlQuery' deprecated in favor of 'makeQueryString'.
function makeUrlQuery(Array $vars) { return makeQueryString($vars); }

function makeQueryString(Array $vars) {
  $filtered = $vars;
  return '?' . implode('&',
    array_map(function($name, $val) { return $name . '=' . urlencode($val); },
              array_keys($filtered), array_values($filtered)));
}

/**
 * Read the query portion of a URI/URL and return a respective associated array.
 * E.g., given "/path/to/resource?a=b&c=123" yield array('a' => 'b', 'c' => 123).
 */
function readQueryFromURI($uri) {
  $parts = explode('?', $uri);
  if (count($parts) > 2) {
    throw new InvalidArgumentException("Multiple question marks found in given URI: $uri");
  }
  return queryToArray(at($parts, 1, ''));
}

/**
 * Given a URI/URL query-string, return a respective associated array.
 * E.g., given "?a=b&c=123" yield array('a' => 'b', 'c' => 123).
 */
function queryToArray($q) {
  if ($q == '' || $q == '?') return array();
  $assignments = explode('&', withoutPrefix($q, '?'));
  $queryVars = array();
  foreach ($assignments as $assignment) {
    $parts = explode('=', $assignment);
    $var = $parts[0];
    $val = null;
    if (count($parts) == 2) $val = $parts[1];
    else if (count($parts) > 2) throw new InvalidArgumentException("Component of query-string " .
                                  "contained multiple equal-signs");
    $queryVars[$var] = urldecode($val);
  }
  return $queryVars;
}

function titleToUrlComponent($title) {
  $string = trim($title);
  $string = str_replace("'", "", $string);
  $string = preg_replace("`\[.*\]`U","", $string);
  $string = preg_replace('`&(amp;)?#?[a-z0-9]+;`i', '-', $string);
  $string = preg_replace('`\s`i', '_', $string);
  //$string = htmlentities($string, ENT_COMPAT, 'utf-8');
  //$string = preg_replace(
  //  "`&([a-z])(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig|quot|rsquo);`i","\\1",
  //  $string);
  $string = preg_replace(array("`[^a-z0-9]`i","`[-]+`"), "-", $string);
  return strtolower(trim($string, '-'));
}
