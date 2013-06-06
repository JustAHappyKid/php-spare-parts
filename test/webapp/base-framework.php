<?php

require_once dirname(__FILE__) . '/harness.php';

use \SpareParts\SelfTesting\WebappTestHarness, \SpareParts\Webapp,
  \SpareParts\Test\HttpNotFound, \SpareParts\Test\HttpRedirect;

class BaseFrameworkTests extends WebappTestHarness {

  function testRoutingToClassMethods() {
    # A method named "twoWords" should map to path-component "two-words"...
    $response = $this->get('/a-controller/two-words');
    assertEqual(200, $response->statusCode);
    # ...but path components 'twowords' and 'twoWords' should not map to that same method...
    foreach (array('twowords', 'twoWords') as $p) {
      $this->assertYields404("/a-controller/$p",
        "Path '/a-controller/$p' should not route to method 'twoWords'");
    }
  }

  function testSpecialMethodsCannotBeAccessedViaRequest() {
    $this->assertYields404('/a-controller/dispatch');
    $this->assertYields404('/a-controller/init');
    assertTrue(method_exists(new AController, 'dispatch'));
    assertTrue(method_exists(new AController, 'init'));
  }

  private function assertYields404($path, $msg = null) {
    try {
      $response = $this->get($path);
      fail($msg ? $msg : "Expected to get 404-not-found response for path '$path'");
    } catch (HttpNotFound $_) {
      # That's what we're expecting!
    }
  }

  function testPathComponentsAreDecoded() {
    $response = $this->get("/hitit/sumthin'%20to%20dec%40de");
    assertTrue(strstr($response->content, "sumthin' to dec@de") != false,
      'Decoded value should be in following output: ' . $response->content);
  }

  function testRedirecting() {
    try {
      $response = $this->get('/do-redirect');
      assertTrue($response->statusCode >= 300 && $response->statusCode < 400);
    } catch (HttpRedirect $e) {
      assertTrue($e->statusCode >= 300 && $e->statusCode < 400);
    }
  }

  # This use-case should not occur in production (unless something's misconfigured?), but
  # we want to make sure our library will raise the expected exception...
  function testAttemptingToRedirectIfHttpHostNotSet() {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = "/do-redirect";
    unset($_SERVER['HTTP_HOST']);
    $fc = new \SpareParts\SelfTesting\FrontControllerForTesting(dirname(__FILE__));
    try {
      $response = $fc->handleRequest();
      fail('Expected exception to be raised since HTTP_HOST is not set');
    } catch (Exception $_) { /* that's what we're looking for! */ }
  }

  function testCaseWhereDirectoryIsMirroredByFileOfSameName() {
    # In the actions directory, both a directory named 'dir1' and a file name 'dir1.php' exist...
    # In the request here, the file (dir1.php) should be ignored, and this should map to
    # 'dir1/dir2/index.php'.
    $r = $this->get('/dir1/dir2/');
    assertTrue(strstr($r->content, 'Welcome to the index of dir2') != false,
      'Expected to reach the action in file dir1/dir2/index.php');
  }
}
