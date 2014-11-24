#!/usr/bin/php -dsendmail_path=mock-sendmail
<?php

use \SpareParts\Test;

function main($argc, $argv) {
  error_reporting(E_ALL);
  $baseDir = realpath(dirname(dirname(__FILE__)));
  set_include_path("$baseDir/src:" . get_include_path());

  require_once 'error-handling.php';
  SpareParts\ErrorHandling\enableErrorHandler();

  require_once 'test/mock-sendmail.php';
  $testEmailsDir = makeTempDir();
  putenv("PHP_SPARE_PARTS_TEST_MAILDIR=$testEmailsDir");
  Test\MockEmail\addMockSendmailToPath("$baseDir/bin/mock-sendmail");

  require_once 'test/base-framework.php';
  $filesToIgnore = array('mock/bin/*', 'network-enabled/*', 'template/*.diet-php', 'test.php',
                         'webapp/actions/*');
  Test\testScriptMain("$baseDir/test", $filesToIgnore, $argc, $argv);
}

main($argc, $argv);
