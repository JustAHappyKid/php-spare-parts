<?php

require_once 'test/web-browser.php';

use \MyPHPLibs\Test, \MyPHPLibs\WebClient\HttpResponse, \MyPHPLibs\Test\WebBrowserForTesting;

class WebBrowserTests extends Test\TestHarness {

  function setUp() {
    $this->wb = new WebBrowserForTesting;
  }

  function testRefererHeaderGetsSent() {
    $this->wb->addMockResponse('GET', 'http://example.org/page',
      array('HTTP/1.1 200 OK', 'Content-Type: text/html', '',
            '<html><body><iframe src="http://example.org/sub-page"> </iframe></body></html>'));
    $this->wb->get('http://example.org/page');
    $nodes = $this->wb->findMatchingNodes('//iframe');
    assertEqual(1, count($nodes));
    assertEqual('http://example.org/sub-page', $nodes[0]->getAttribute('src'));
    // XXX: And then...?
  }
}
