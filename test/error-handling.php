<?php

require_once 'error-handling.php';

use \MyPHPLibs\ErrorHandling;

function testGeneratingErrorReportFromException() {
  ErrorHandling\constructErrorReport(new Exception('catch me if u can!'));
}
