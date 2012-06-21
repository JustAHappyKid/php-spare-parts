<?php

require_once 'test/web-browser.php';

use \MyPHPLibs\Test, \MyPHPLibs\WebClient\HttpClient, \MyPHPLibs\WebClient\HostNameResolutionError,
  \MyPHPLibs\WebClient\HttpConnectionError;

# Note, our mock-client class extends from the WebBrowser class (which in turn extends from
# the HttpClient class), to allow for broader testing purposes; so, we use a sub-class of the
# WebBrowser for testing what is properly the functionality of the HttpClient, but it
# shouldn't make a difference (since WebBrowser just adds functionality to HttpClient).
use \MyPHPLibs\Test\WebBrowserForTesting;

class HttpClientTests extends Test\TestHarness {

  function setUp() {
    $this->client = new WebBrowserForTesting;
  }

  function assertWebBrowserForTestingExtendsFromHttpClient() {
    assertTrue($this->client instanceof HttpClient,
      "The WebBrowserForTesting should extend from the HttpClient class");
  }

  function testRefererHeaderGetsSent() {
    $this->client->setNextMockResponse(array(
      'HTTP/1.1 200 OK',
      'Server: Fake-Madeup',
      'Content-Length: 11',
      'Content-Type: text/plain; charset=utf-8',
      '',
      'Hey booger.'));
    $this->client->post('http://somefunnypranks.com/pages/2', array(),
                  array('Referer' => 'http://somefunnypranks.com/'));
    $refererLineFound = false;
    foreach ($this->client->headersSent as $header) {
      if ($header == 'Referer: http://somefunnypranks.com/') {
        $refererLineFound = true;
      }
    }
    assertTrue($refererLineFound);
  }

  function testContentLengthHeaderIsNotRequired() {
    $header = array('HTTP/1.1 200 OK', 'Content-Type: text/plain; charset=utf-8', '');
    $this->client->setNextMockResponse(array_merge($header, array('Hi, this is it.')));
    $r = $this->client->get('http://somecrummysite.com/');
    assertEqual('Hi, this is it.', $r->content);
    $body = str_repeat('x', 1030);
    $this->client->setNextMockResponse(array_merge($header, array($body)));
    $r = $this->client->get('http://somecrummysite.com/other-page');
    assertEqual($body, $r->content);
  }

  function testCookieHeaderDoesNotHaveTrailingSemicolon() {
    $header = array('HTTP/1.1 200 OK', 'Content-Type: text/html',
                    'Set-Cookie: cookie1=abc123;path=/', 'Set-Cookie: c2=987zyx;path=/', '');
    $this->client->setNextMockResponse(array_merge($header, array('Eat my cookies.')));
    $this->client->get('http://suckysite.com/');
    $this->client->setNextMockResponse(array('HTTP/1.1 200 OK', 'Content-Type: text/html', '',
                                             'What cookies did I give you?'));
    $this->client->get('http://suckysite.com/other-page');
    $cookieLines = array();
    foreach ($this->client->headersSent as $header) {
      if (substr($header, 0, 7) == 'Cookie:') {
        $cookieLines []= $header;
      }
    }
    assertEqual(1, count($cookieLines));
    $cookieAssignments = explode(';', substr($cookieLines[0], 7));
    assertEqual(2, count($cookieAssignments));
  }
}

class TestHttpClientHandles30xRedirectResponsesProperly extends Test\TestHarness {

  function setUp() {
    $this->client = new WebBrowserForTesting;
    $this->client->follow_redirect = true;
  }

  function testCurrentLocationFieldIsSetUponRedirect() {
    $this->client->addMockResponse('GET', 'http://site.com/redirect-me',
      array('HTTP/1.1 302 Found', 'Location: http://site.com/other-page', '', ''));
    $this->client->addMockResponse('GET', 'http://site.com/other-page',
      array('HTTP/1.1 200 OK', 'Content-Type: text/plain', '', 'you got it'));
    $this->client->get('http://site.com/redirect-me');
    assertEqual('http://site.com/other-page', $this->client->currentLocation);
  }

  function testHandlingLocationHeaderWithoutAbsoluteURI() {
    $this->client->addMockResponse('GET', 'http://site.com/redirect-to-same-server',
      array('HTTP/1.1 302 Found', 'Location: /another-page', '', ''));
    $this->client->addMockResponse('GET', 'http://site.com/another-page',
      array('HTTP/1.1 200 OK', 'Content-Type: text/plain', '', 'uh-huh'));
    $this->client->get('http://site.com/redirect-to-same-server');
    assertEqual('http://site.com/another-page', $this->client->currentLocation);
  }

  function testHandlingLocationHeaderWithOnlyRelativePath() {
    $this->client->addMockResponse('GET', 'http://site.com/pub/relative-redirect',
      array('HTTP/1.1 302 Found', 'Location: also-in-pub-dir', '', ''));
    $this->client->addMockResponse('GET', 'http://site.com/pub/also-in-pub-dir',
      array('HTTP/1.1 200 OK', 'Content-Type: text/plain', '', 'yup'));
    $this->client->get('http://site.com/pub/relative-redirect');
    assertEqual('http://site.com/pub/also-in-pub-dir', $this->client->currentLocation);
  }
}

# ----------------------------------------------------------------------------------------------
# - Test Support for "chunked" Transfer-Encoding
# ----------------------------------------------------------------------------------------------

class TestHttpClientProperlySupportsChunkedTransferEncoding extends Test\TestHarness {

  function testCaseWhereChunkDoesNotEndWithNewline() {
    $client = new WebBrowserForTesting;
    $client->addMockResponse('GET', 'http://test.com/page1',
      array(
        'HTTP/1.1 200 OK', 'Content-Type: text/plain', 'Transfer-Encoding: chunked', '',
        '3',
        'one',
        '5',
        ' sing',
        '8',
        'le line!',
        '0', '', ''));
    $r = $client->get('http://test.com/page1');
    assertEqual('one single line!', $r->content);
  }

  function testCaseWhereAtLeastOneChunkIsLargerThan1024BufferSize() {
    $contentChunk1 = "I know my alphabets:\r\n" .
      str_repeat("abc", HttpClient::defaultChunkSize / 3);
    $contentChunk2 = "\r\n\r\nWhatd'ya think?!";
    $client = new WebBrowserForTesting;
    $client->addMockResponse('GET', 'http://test.com/page2',
      array(
        'HTTP/1.1 200 OK', 'Content-Type: text/plain', 'Transfer-Encoding: chunked', '',
        dechex(strlen($contentChunk1)), $contentChunk1,
        dechex(strlen($contentChunk2)), $contentChunk2, '0', '', ''));
    $r = $client->get('http://test.com/page2');
    assertEqual($contentChunk1 . $contentChunk2, $r->content);
  }
}


class ToTestCaseWhereFreadDoesNotReturnContentOfGivenLength extends WebBrowserForTesting {
  protected function fread($conn, $maxLength) {
    if ($maxLength % 2 == 0 /* An even number? */) $maxLength -= 1;
    return parent::fread($conn, $maxLength);
  }
}

# This test case arises from a very peculiar situation indeed...  For some reason, there are
# some cases (some websites) for which the call to 'fread', requesting 2 bytes, only returns
# 1 (one) byte, despite the fact there are 2 more in the stream...
function testCaseWhereReadingEndOfDataChunkRequiresTwoCallsToFread() {
  $client = new ToTestCaseWhereFreadDoesNotReturnContentOfGivenLength;
  $client->addMockResponse('GET', 'http://test.com/ok',
    array(
      'HTTP/1.1 200 OK', 'Content-Type: text/plain', 'Transfer-Encoding: chunked', '',
      dechex(19),
      'This is it, really.',
      '0', '', ''));
  $r = $client->get('http://test.com/ok');
  assertEqual('This is it, really.', $r->content);
}

# ----------------------------------------------------------------------------------------------
# - Test HTTPS Capability
# ----------------------------------------------------------------------------------------------

function testSecureHTTPConnectionIsMadeProperly() {
  $client = new ForTestingHTTPSConnectionProperlyInitiated;
  $client->expectHostnameForSocketCreation('ssl://securesite.com');
  $client->setNextMockResponse(array('HTTP/1.1 200 OK', 'Content-Type: text/plain', '',
                                           'Served securely!'));
  $client->get('https://securesite.com/');
}

class ForTestingHTTPSConnectionProperlyInitiated extends WebBrowserForTesting {
  private $expectedHostname = null;
  public function expectHostnameForSocketCreation($hn) {
    $this->expectedHostname = $hn;
  }
  protected function fsockopen($hostname, $port, &$errno, &$errstr, $timeout = null) {
    if ($this->expectedHostname) assertEqual($this->expectedHostname, $hostname);
    return parent::fsockopen($hostname, $port, $errno, $errstr, $timeout);
  }
}

# This was a weird case...  Either, we're dealing with a flaky server, or I'm not understanding
# something regarding the way that HTTP/TCP connections are to be deablt with...  But, anyway,
# one server was giving us trouble because 'feof' would return *false* on the first attempt
# (even though no further data was queued to come through); but after calling 'fread', even
# though it returns no further data, subsequent calls to 'feof' would return true...
# I'm not sure if it's relevant, but it should be noted that, for the given response, this
# server was NOT providing a Content-Length header, thought it WAS providing a
# "Connection: close" header.
class ToTestConnectionThatNeedsExtraEndOfFileCheck extends WebBrowserForTesting {

  private $extraCallToFreadMade = false;

  protected function fread($conn, $maxLength) {
    $buf = parent::fread($conn, $maxLength);
    if ($buf == '') $this->extraCallToFreadMade = true;
    return $buf;
  }

  protected function feof($conn) {
    return parent::feof($conn) && $this->extraCallToFreadMade;
  }
}

function testHandlingOfFlakyServer() {
  $client = new ToTestConnectionThatNeedsExtraEndOfFileCheck;
  $header = array('HTTP/1.1 200 OK', 'Content-Type: text/plain; charset=utf-8', '');
  $client->setNextMockResponse(array_merge($header, array('Hi, this is it.')));
  $r = $client->get('http://somecrummysite.com/');
  assertEqual('Hi, this is it.', $r->content);
}

# ----------------------------------------------------------------------------------------------
# - Testing recoverability from errors/exceptions...
# ----------------------------------------------------------------------------------------------

class ToTestRecoveringFromFailedHostnameResolution extends WebBrowserForTesting {
  private $numTimesInvoked = 0;
  protected function gethostbyname($domain) {
    $this->numTimesInvoked++;
    # Returning the domain indicates failure (see http://php.net/gethostbyname)
    return $this->numTimesInvoked > 1 ? '99.99.99.99' : $domain; 
  }
}

function testRecoveringFromFailedHostnameResolution() {
  $client = new ToTestRecoveringFromFailedHostnameResolution;
  $client->setNextMockResponse(genericMockResponse());
  try {
    $r = $client->get('http://www.myplace.com/some-page');
    fail('Expected te get HostNameResolutionError');
  } catch (HostNameResolutionError $_) { /* That's what we're expecting... */ }
  $client->get('http://www.myplace.com/try-again');
}

class ToTestRecoveringFromConnectionError extends WebBrowserForTesting {
  private $numTimesInvoked = 0;
  protected function fgets($conn, $len) {
    $this->numTimesInvoked++;
    return $this->numTimesInvoked > 1 ? parent::fgets($conn, $len) : ''; 
  }
}

function testRecoveringFromConnectionError() {
  $client = new ToTestRecoveringFromConnectionError;
  $client->setNextMockResponse(genericMockResponse('Hi there.'));
  try {
    $r = $client->get('http://www.myplace.com/some-page');
    fail('Expected te get HttpConnectionError');
  } catch (HttpConnectionError $_) { /* That's what we're expecting... */ }
  $r = $client->get('http://www.myplace.com/try-again');
  assertEqual('Hi there.', $r->content);
}

# ----------------------------------------------------------------------------------------------
# - Helper function
# ----------------------------------------------------------------------------------------------

function genericMockResponse($content = 'Just some kinda stuff.') {
  return array('HTTP/1.1 200 OK', 'Content-Type: text/plain; charset=utf-8', '', $content);
}
