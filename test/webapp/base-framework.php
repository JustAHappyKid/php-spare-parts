<?php

require_once 'webapp/base-framework.php';

use \MyPHPLibs\Webapp;

class FrontControllerForTesting extends Webapp\FrontController {
  protected function info($msg) { }
  protected function notice($msg) { }
  protected function warn($msg) { }
  protected function sessionLifetimeInSeconds() { return 60 * 60 * 3; /* 3 hours */ }
  protected function checkAccessPrivileges($cmd, $user) { return true; }
  
  # XXX: Should we not have a base-line implementation of this in the base FrontController??
  protected function renderAndOutputPage($page) {
    $response = new Webapp\ResponseObj;
    $response->statusCode = 200;
    $response->contentType = $page->contentType;
    $response->content = $page->body;
    return $response;
  }
}

function testPathComponentsAreDecoded() {
  $_SERVER['REQUEST_METHOD'] = 'GET';
  $_SERVER['REQUEST_URI'] = "/hitit/sumthin'%20to%20dec%40de";
  $fc = new FrontControllerForTesting(dirname(__FILE__));
  $response = $fc->handleRequest();
  assertTrue(strstr($response->content, "sumthin' to dec@de") != false,
    'Decoded value should be in following output: ' . $response->content);
}
