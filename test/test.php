#!/usr/bin/php -dsendmail_path=mock-sendmail
<?php

use \SpareParts\Test;

function main($argc, $argv) {
  error_reporting(E_ALL);
  $baseDir = realpath(dirname(dirname(__FILE__)));
  set_include_path("$baseDir/src:" . get_include_path());
  require_once 'error-handling.php';
  SpareParts\ErrorHandling\enableErrorHandler();
  configMockSendmail("$baseDir/test");
  require_once $baseDir . '/src/test/base-framework.php';
  $filesToIgnore = array('mock/bin/*', 'network-enabled/*', 'template/*.diet-php', 'test.php',
                         'webapp/actions/*');
  Test\testScriptMain("$baseDir/test", $filesToIgnore, $argc, $argv);
}

# Assert 'sendmail_path' was properly configured as appropriate for testing environment
# and place its directory in the PATH environment variable...
function configMockSendmail($testDir) {
  $smPath = ini_get("sendmail_path");
  if ($smPath != 'mock-sendmail') {
    throw new Exception("Expected 'sendmail_path' config variable to be set to " .
      "'mock-sendmail' but it is set as '$smPath'");
  }
  putenv("PATH=$testDir/mock/bin:" . getenv('PATH'));
}

main($argc, $argv);
