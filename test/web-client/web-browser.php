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

  function testFollowingMetaTagRefresh() {
    $this->wb->addMockResponse('GET', 'http://test.org/from',
      array('HTTP/1.1 200 OK', 'Content-Type: text/html', '', '
        <html>
          <head><meta http-equiv="refresh" content="0; url=http://test.org/to"></head>
          <body><p>You should have been redirected.</p></body>
        </html>'));
    $this->wb->addMockResponse('GET', 'http://test.org/to',
      array('HTTP/1.1 200 OK', 'Content-Type: text/html', '',
            '<html><body><p>Yeah, you found it!</p></body></html>'));
    $this->wb->get('http://test.org/from');
    assertEqual('http://test.org/to', $this->wb->currentLocation);
  }
}
