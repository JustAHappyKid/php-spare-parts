<?php

namespace MyPHPLibs\WebBrowsing;

require_once dirname(__FILE__) . '/http-client.php';
require_once dirname(__FILE__) . '/html-parsing.php';

class WebBrowser extends \HttpClient {
  protected $lastResponse;

  public function get($url) {
    $this->lastResponse = parent::get($url);
    return $this->lastResponse;
  }

  public function post($url, $postValues, $extraHeaders = array()) {
    $this->lastResponse = parent::post($url, $postValues, $extraHeaders);
    return $this->lastResponse;
  }

  public function findMatchingNodes($xpathExpr) {
    $xpathObj = new \DOMXPath(htmlSoupToDOMDocument($this->lastResponse->content, null));
    return findNodes($xpathObj, $xpathExpr);
  }
}
