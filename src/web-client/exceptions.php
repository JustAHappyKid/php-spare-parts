<?php

namespace SpareParts\WebClient;

use \Exception;

class NetworkError extends Exception {}
class HttpConnectionError extends NetworkError {}
class HostNameResolutionError extends NetworkError {}
class HttpProtocolError extends Exception {}
class TooManyRedirects extends HttpProtocolError {}

class HttpClientRedirect extends Exception {
  public $location, $statusCode;
  function __construct($location, $code = null) {
    $this->location = $location;
    $this->statusCode = $code;
  }
}
