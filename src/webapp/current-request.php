<?php

namespace SpareParts\Webapp\CurrentRequest;

require_once dirname(__FILE__) . '/../url.php';   # makeUrlQuery

use \Exception, \SpareParts\URL;

function isPostRequest() {
  return strtolower($_SERVER['REQUEST_METHOD']) == 'post';
}

function isGetRequest() {
  return strtolower($_SERVER['REQUEST_METHOD']) == 'get';
}

function getProtocol() {
  return 'http' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 's' : '');
}

function getHost() {
  if (empty($_SERVER['HTTP_HOST'])) {
    throw new Exception("\$_SERVER['HTTP_HOST'] is empty");
  }
  return $_SERVER['HTTP_HOST'];
}

function getURL() {
  return getProtocol() . '://' . getHost() . $_SERVER['REQUEST_URI'];
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
