<?php

require_once 'http-client.php';

use \MyPHPLibs\Test;

class HttpClientTests extends Test\TestHarness {

  function setUp() {
    $this->client = new HttpClientForTesting;
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

  function testCurrentLocationFieldIsSetUponRedirect() {
    $this->client->follow_redirect = true;
    $this->client->addResponseForPath('GET', '/redirect-me',
      array('HTTP/1.1 302 Found', 'Location: http://site.com/other-page', '', ''));
    $this->client->addResponseForPath('GET', '/other-page',
      array('HTTP/1.1 200 OK', 'Content-Type: text/plain', '', 'you got it'));
    $this->client->get('http://site.com/redirect-me');
    assertEqual('http://site.com/other-page', $this->client->currentLocation);
  }

  function testHandlingLocationHeaderWithoutAbsoluteURI() {
    $this->client->follow_redirect = true;
    $this->client->addResponseForPath('GET', '/redirect-to-same-server',
      array('HTTP/1.1 302 Found', 'Location: /another-page', '', ''));
    $this->client->addResponseForPath('GET', '/another-page',
      array('HTTP/1.1 200 OK', 'Content-Type: text/plain', '', 'uh-huh'));
    $this->client->get('http://site.com/redirect-to-same-server');
    assertEqual('http://site.com/another-page', $this->client->currentLocation);
  }

  function testHandlingLocationHeaderWithOnlyRelativePath() {
    $this->client->follow_redirect = true;
    $this->client->addResponseForPath('GET', '/pub/relative-redirect',
      array('HTTP/1.1 302 Found', 'Location: also-in-pub-dir', '', ''));
    $this->client->addResponseForPath('GET', '/pub/also-in-pub-dir',
      array('HTTP/1.1 200 OK', 'Content-Type: text/plain', '', 'yup'));
    $this->client->get('http://site.com/pub/relative-redirect');
    assertEqual('http://site.com/pub/also-in-pub-dir', $this->client->currentLocation);
  }

  function testSecureHTTPConnectionIsMadeProperly() {
    $this->client->expectHostnameForSocketCreation('ssl://securesite.com');
    $this->client->setNextMockResponse(array('HTTP/1.1 200 OK', 'Content-Type: text/plain', '',
                                             'Served securely!'));
    $this->client->get('https://securesite.com/');
  }
}

class HttpClientForTesting extends HttpClient {

  public $requestLine = null;
  public $headersSent = array();
  public $finishedSendingHeaders = false;
  private $expectedHostname = null;
  private $responsesForPaths = array();
  private $mockResponse;
  //private $lastLineSent = 0;
  private $numBytesSent = 0;

  public function expectHostnameForSocketCreation($hn) {
    $this->expectedHostname = $hn;
  }

  public function setNextMockResponse($lines) {
    $this->mockResponse = implode("\r\n", $lines);
  }

  public function addResponseForPath($method, $path, $lines) {
    $this->responsesForPaths[$method][$path] = $lines;
  }

  /*protected function makeRequest($url, $extraArguments = null) {
    $this->numBytesSent = 0;
    $this->requestLine = null;
    $this->headersSent = array();
    $this->finishedSendingHeaders = false;
    return parent::makeRequest($url, $extraArguments);
  }*/

  protected function sendRequest($args) {
    $m = $args['RequestMethod'];
    $p = $args['RequestURI'];
    if (isset($this->responsesForPaths[$m][$p])) {
      $this->setNextMockResponse($this->responsesForPaths[$m][$p]);
    }
    parent::sendRequest($args);
  }

  protected function open($args) {
    $this->numBytesSent = 0;
    $this->requestLine = null;
    $this->headersSent = array();
    $this->finishedSendingHeaders = false;
    parent::open($args);
  }

  /*protected function connect($hostName, $hostPort, $ssl, $serverType = 'HTTP') {
    $this->state = "Connected";
  }

  protected function disconnect() {
    $this->state = "Disconnected";
  }*/

  function putLine($line) {
    if ($this->requestLine === null) {
      $this->requestLine = $line;
    } else if (!$this->finishedSendingHeaders) {
      if ($line === "") {
        $this->finishedSendingHeaders = true;
      } else {
        $this->headersSent[] = $line;
      }
    }
  }

  function putData($data) { }
  function flushData() { }

  /*function getLine() {
    return $this->mockResponse[$this->lastLineSent++];
  }*/

  /*function readBytes($length) {
    return $this->mockResponse[$this->lastLineSent++];
  }*/

  /*function endOfInput() {
    return $this->lastLineSent >= count($this->mockResponse);
  }*/

  /*
  function setDataAccessError($error, $checkConnection = 0) {
    fail("setDataAccessError called with following error message: $error");
  }
  */

  protected function gethostbyname($domain) {
    return '12.34.56.78';
  }

  protected function fsockopen($hostname, $port, &$errno, &$errstr, $timeout = null) {
    if ($this->expectedHostname) assertEqual($this->expectedHostname, $hostname);
    return "this is the connection object, i guess";
  }

  protected function fgets($conn, $maxLengthPlusOne) {
    $newlinePos = strpos($this->mockResponse, "\n", $this->numBytesSent);
    $len = ($newlinePos === false) ? $maxLengthPlusOne - 1 : $newlinePos - $this->numBytesSent + 1;
    $buf = substr($this->mockResponse, $this->numBytesSent, $len);
    $this->numBytesSent += strlen($buf);
    return $buf;
  }

  protected function fread($conn, $maxLength) {
    $buf = substr($this->mockResponse, $this->numBytesSent, $maxLength);
    $this->numBytesSent += strlen($buf);
    return $buf;
  }

  protected function feof($conn) {
    return $this->numBytesSent >= strlen($this->mockResponse);
  }

  protected function fclose($handle) {
    return true;
  }

  protected function socket_get_status($conn) {
    return array("stream_type" => "tcp_socket/xxx", "mode" => "r+", "unread_bytes" => 0,
                 "seekable" => false, "timed_out" => false, "blocked" => true, "eof" => true);
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
class ToTestConnectionThatNeedsExtraEndOfFileCheck extends HttpClientForTesting {

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

class ToTestRecoveringFromFailedHostnameResolution extends HttpClientForTesting {
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

class ToTestRecoveringFromConnectionError extends HttpClientForTesting {
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
