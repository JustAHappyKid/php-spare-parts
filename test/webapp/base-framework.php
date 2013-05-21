<?php

require_once 'webapp/base-framework.php';

use \SpareParts\Test\TestHarness, \SpareParts\Webapp;

class BaseFrameworkTests extends TestHarness {

  function testPathComponentsAreDecoded() {
    $response = $this->mockRequest('GET', "/hitit/sumthin'%20to%20dec%40de");
    assertTrue(strstr($response->content, "sumthin' to dec@de") != false,
      'Decoded value should be in following output: ' . $response->content);
  }

  function testRedirecting() {
    $response = $this->mockRequest('GET', '/do-redirect');
    assertTrue($response->statusCode >= 300 && $response->statusCode < 400);
  }

  # This use-case should not occur in production (unless something's misconfigured?), but
  # we want to make sure our library will raise the expected exception...
  function testAttemptingToRedirectIfHttpHostNotSet() {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = "/do-redirect";
    unset($_SERVER['HTTP_HOST']);
    $fc = new FrontControllerForTesting(dirname(__FILE__));
    try {
      $response = $fc->handleRequest();
      fail('Expected exception to be raised since HTTP_HOST is not set');
    } catch (Exception $_) { /* that's what we're looking for! */ }
  }

  function testCaseWhereDirectoryIsMirroredByFileOfSameName() {
    $r = $this->mockRequest('GET', '/dir1/dir2/');
    assertTrue(strstr($r->content, 'Welcome to the index of dir2') != false,
      'Expected to reach the action in file dir1/dir2/index.php');
  }

  private function mockRequest($method, $uri) {
    $_SERVER['HTTP_HOST'] = 'test.net';
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $fc = new FrontControllerForTesting(dirname(__FILE__));
    return $fc->handleRequest();
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
