<?php

require_once 'error-handling.php';

use \SpareParts\ErrorHandling;

function testGeneratingErrorReportFromException() {
  $r = ErrorHandling\constructErrorReport(new Exception('catch me if u can!'));
  assertTrue(strlen($r) > 0);
}

/**
 * A simple sanity check, to make sure 'presentErrorReport' at least doesn't blow up,
 * in both use-cases (with "display_errors" on versus off).
 */
function testSanityOfPresentErrorReport() {
  $_SERVER['HTTP_HOST'] = 'test.com';
  ob_start();
  ErrorHandling\presentErrorReport("the report!");
  ErrorHandling\presentErrorReport("the report!", "guy@example.com");
  ob_end_clean();
}

function testSanityOfRespondToError() {
  $_SERVER['HTTP_HOST'] = 'test.com';
  ob_start();
  $orig = ini_set('display_errors', false);
  ErrorHandling\respondToError("the report!", null);
  $orig = ini_set('display_errors', true);
  ErrorHandling\respondToError("the report!", "guy@example.com");
  $orig = ini_set('display_errors', $orig);
  $output = ob_get_contents();
  assert(contains($output, "the report"));
  ob_end_clean();
}
