<?php

namespace SpareParts\Test;

require_once dirname(dirname(__FILE__)) . '/web-client/web-browser.php';

use \Exception, \Closure, \SpareParts\WebClient\WebBrowser, \SpareParts\WebClient\HttpRequest;

# This sub-class of WebBrowser (and, in turn, HttpClient) is for testing purposes.
# It's not only used for testing the functionality of the WebBrowser and HttpClient
# classes itself, but is also useful for mocking genuine HTTP network
# activity for other code making use of the WebBrowser/HttpClient interface.
class WebBrowserForTesting extends WebBrowser {

  public $requestLine = null;
  public $headersSent = array();
  public $finishedSendingHeaders = false;
  protected $mockResponses = array();
  protected $mockResponse;
  protected $numBytesSent = 0;

  public function setNextMockResponse(Array $lines) {
    $this->mockResponse = implode("\r\n", $lines);
  }

  public function addMockResponse($method, $url, $lines) {
    $this->mockResponses[$method][$url] = $lines;
  }

  public function addHookForURL($method, $url, Closure $hook) {
    $this->mockResponses[$method][$url] = $hook;
  }

  protected function sendRequest(HttpRequest $req) {
    $method = $req->method;
    $url = $req->protocol . '://' . $req->hostName . $req->relativeURI;
    $resp = @ $this->mockResponses[$method][$url];
    if ($resp) {
      if (is_array($resp)) {
        $this->setNextMockResponse($this->mockResponses[$method][$url]);
      } else if ($resp instanceof Closure) {
        $this->setNextMockResponse($resp($req));
      } else {
        throw new Exception('Expected array or Closure');
      }
    } else if (empty($this->mockResponse)) {
      fail('WebBrowserForTesting->sendRequest invoked but no "mock response" is prepared ' .
           'for method ' . $method . ' and URL ' . $url);
    }
    parent::sendRequest($req);
  }

  protected function open(HttpRequest $req) {
    $this->numBytesSent = 0;
    $this->requestLine = null;
    $this->headersSent = array();
    $this->finishedSendingHeaders = false;
    parent::open($req);
  }

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

  protected function gethostbyname($domain) {
    return '12.34.56.78';
  }

  protected function fsockopen($hostname, $port, &$errno, &$errstr, $timeout = null) {
    return "this is the connection object, i guess";
  }

  protected function fgets($conn, $maxLengthPlusOne) {
    $newlinePos = strpos($this->mockResponse, "\n", $this->numBytesSent);
    $len = ($newlinePos === false) ? $maxLengthPlusOne - 1 : $newlinePos - $this->numBytesSent + 1;
    $buf = substr($this->mockResponse, $this->numBytesSent, $len);
    $this->numBytesSent += strlen($buf);
    $this->unsetMockResponseIfFinishedServing();
    return $buf;
  }

  protected function fread($conn, $maxLength) {
    $buf = substr($this->mockResponse, $this->numBytesSent, $maxLength);
    $this->numBytesSent += strlen($buf);
    $this->unsetMockResponseIfFinishedServing();
    return $buf;
  }

  private function unsetMockResponseIfFinishedServing() {
    if ($this->numBytesSent >= strlen($this->mockResponse)) $this->mockResponse = null;
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
