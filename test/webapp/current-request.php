<?php

require_once 'webapp/current-request.php';

use \MyPHPLibs\Webapp\CurrentRequest;

function testGettingURLForCurrentRequest() {
  $origServerVars = isset($_SERVER) ? $_SERVER : null;
  $_SERVER['HTTP_HOST'] = 'test.com';
  $_SERVER['REQUEST_URI'] = '/some/path';
  $_SERVER['HTTPS'] = null;
  assert(CurrentRequest\getURL() == 'http://test.com/some/path');
  $_SERVER['HTTPS'] = 'on';
  assert(CurrentRequest\getURL() == 'https://test.com/some/path');
  $_SERVER = $origServerVars;
}

function testIsSecureHttpConnection() {
  unset($_SERVER['HTTPS']);
  assertFalse(CurrentRequest\isSecureHttpConnection());
  $_SERVER['HTTPS'] = 'off';
  assertFalse(CurrentRequest\isSecureHttpConnection());
  $_SERVER['HTTPS'] = 'on';
  assertTrue(CurrentRequest\isSecureHttpConnection());
}
