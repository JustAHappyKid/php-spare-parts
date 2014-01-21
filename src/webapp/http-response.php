<?php

namespace SpareParts\Webapp;

require_once dirname(dirname(__FILE__)) . '/http.php';
use \SpareParts\HTTP;

class HttpResponse extends HTTP\Response {

  public $contentType;

  function __construct($statusCode = null, $contentType = null, $content = null) {
    $this->statusCode = $statusCode;
    $this->contentType = $contentType;
    $this->content = $content;
  }
}

function htmlResponse($html, $charset = null) {
  $r = new HttpResponse();
  $r->content = $html;
  $r->contentType = 'text/html' . ($charset ? "charset=$charset" : "");
  $r->statusCode = 200;
  return $r;
}
