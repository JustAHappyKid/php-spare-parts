<?php

namespace SpareParts\SelfTesting;

require_once 'webapp/base-framework.php'; # FrontController
require_once 'test/webapp.php';           # WebappTestingHarness

use \SpareParts\Test\WebappTestingHarness, \SpareParts\Webapp;

class WebappTestHarness extends WebappTestingHarness {

  protected function dispatchToWebapp() {
    global $__frontControllerForTesting;
    $__frontControllerForTesting->go();
    return $__frontControllerForTesting->lastResponse;
  }

  protected function getValidationErrors() {
    echo "WARN: getValidationErrors is not yet implemented.\n";
    return array();
  }
}

class FrontControllerForTesting extends Webapp\FrontController {

  protected function info($msg) { }
  protected function notice($msg) { }
  protected function warn($msg) { }
  protected function sessionLifetimeInSeconds() { return 60 * 60 * 3; /* 3 hours */ }
  protected function checkAccessPrivileges($cmd, $user) { return true; }

  # Redefine both of these as public for testing purposes.
  public function nameOfSessionCookie() { return parent::nameOfSessionCookie(); }
  public function configureAndStartSession() { return parent::configureAndStartSession(); }
  protected function sessionStart() { /* Do nothing -- override call to session_start */ }

  public $lastResponse;
  protected function outputHttpResponse(Webapp\HttpResponse $r) {
    return $this->lastResponse = $r;
  }
}

global $__frontControllerForTesting;
$__frontControllerForTesting = new FrontControllerForTesting(dirname(__FILE__));
