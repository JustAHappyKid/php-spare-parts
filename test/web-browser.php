<?php

require_once 'web-browser.php';

use MyPHPLibs\Test, MyPHPLibs\WebBrowsing\WebBrowser;

class WebBrowserTests extends Test\TestHarness {

  function setUp() {
    $this->wb = new WebBrowserForTesting;
  }

  function testRefererHeaderGetsSent() {
    $response = new HttpResponse('
      <html><iframe src="http://example.org/sub-page"> </iframe></html>');
    $response->statusCode = 200;
    $this->wb->setNextResponseObj($response);
    $this->wb->get('http://example.org/page');
    $nodes = $this->wb->findMatchingNodes('//iframe');
    assertEqual(1, count($nodes));
    assertEqual('http://example.org/sub-page', $nodes[0]->getAttribute('src'));
  }
}

class WebBrowserForTesting extends WebBrowser {
  private $nextResponse;
  public function setNextResponseObj($r) {
    $this->nextResponse = $r;
  }
  protected function makeRequest($url, $extraArguments = null) {
    return $this->nextResponse;
  }
}
