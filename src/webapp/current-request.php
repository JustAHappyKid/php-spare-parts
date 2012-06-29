<?php

namespace MyPHPLibs\Webapp\CurrentRequest;

require_once dirname(__FILE__) . '/../url.php';   # makeUrlQuery

use \Exception, \MyPHPLibs\URL;

function isPostRequest() {
  return strtolower($_SERVER['REQUEST_METHOD']) == 'post';
}

function isGetRequest() {
  return strtolower($_SERVER['REQUEST_METHOD']) == 'get';
}

function getURL() {
  if (empty($_SERVER['HTTP_HOST'])) {
    throw new Exception('HTTP_HOST not set, so cannot construct URL');
  }
  return 'http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 's' : '') .
    '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

function getPath() {
  return current(explode('?', $_SERVER['REQUEST_URI']));
}

function getLocationWithModifiedQuery($paramsToModify) {
  return getPath() . URL\makeUrlQuery(array_merge($_GET, $paramsToModify));
}

function isSecureHttpConnection() {
  return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on';
}
