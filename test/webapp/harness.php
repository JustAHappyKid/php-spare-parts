<?php

namespace SpareParts\SelfTesting;

require_once 'webapp/base-framework.php'; # FrontController
require_once 'test/webapp.php';           # WebappTestingHarness

use \SpareParts\Test\WebappTestingHarness, \SpareParts\Webapp;

class WebappTestHarness extends WebappTestingHarness {

  protected function dispatchToWebapp() {
    global $__frontControllerForTesting;
    $r = $__frontControllerForTesting->handleRequest();
    return $r;
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

  # XXX: Should we not have a base-line implementation of this in the base FrontController??
  protected function renderAndOutputPage($page) {
    $response = new Webapp\HttpResponse;
    $response->statusCode = 200;
    $response->contentType = $page->contentType;
    $response->content = $page->body;
    return $response;
  }
}

global $__frontControllerForTesting;
$__frontControllerForTesting = new FrontControllerForTesting(dirname(__FILE__));
