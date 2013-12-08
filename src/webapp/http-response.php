<?php

namespace SpareParts\Webapp;

class HttpResponse {

  public $statusCode, $contentType, $content;
  private $headers = array();

  function __construct($statusCode = null, $contentType = null, $content = null) {
    $this->statusCode = $statusCode;
    $this->contentType = $contentType;
    $this->content = $content;
  }

  public function addHeader($name, $value) {
    if (empty($this->headers[$name])) $this->headers[$name] = array();
    $this->headers[$name] []= $value;
  }

  public function headersSet() { return array_keys($this->headers); }

  public function getValuesForHeader($name) {
    return isset($this->headers[$name]) ? $this->headers[$name] : array();
  }
}

function htmlResponse($html, $charset = null) {
  $r = new HttpResponse();
  $r->content = $html;
  $r->contentType = 'text/html' . ($charset ? "charset=$charset" : "");
  $r->statusCode = 200;
  return $r;
}
