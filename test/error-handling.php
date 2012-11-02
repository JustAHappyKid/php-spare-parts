<?php

require_once 'error-handling.php';

use \MyPHPLibs\ErrorHandling;

function testGeneratingErrorReportFromException() {
  $r = ErrorHandling\constructErrorReport(new Exception('catch me if u can!'));
  assertTrue(strlen($r) > 0);
}
