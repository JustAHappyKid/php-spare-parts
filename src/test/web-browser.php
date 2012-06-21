<?php

namespace MyPHPLibs\Test;

require_once dirname(dirname(__FILE__)) . '/web-browser.php';

use \MyPHPLibs\WebClient\WebBrowser;

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

  public function setNextMockResponse($lines) {
    $this->mockResponse = implode("\r\n", $lines);
  }

  public function addMockResponse($method, $url, $lines) {
    $this->mockResponses[$method][$url] = $lines;
  }

  protected function sendRequest($args) {
    $method = $args['RequestMethod'];
    $url = $args["Protocol"] . '://' . $args["HostName"] . $args['RequestURI'];
    if (isset($this->mockResponses[$method][$url])) {
      $this->setNextMockResponse($this->mockResponses[$method][$url]);
    } else if (empty($this->mockResponse)) {
      fail('WebBrowserForTesting->sendRequest invoked but no "mock response" is prepared ' .
           'for method ' . $method . ' and URL ' . $url);
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
