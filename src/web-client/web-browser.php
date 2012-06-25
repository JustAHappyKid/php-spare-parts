<?php

namespace MyPHPLibs\WebClient;

require_once dirname(__FILE__) . '/http-client.php';
require_once dirname(__FILE__) . '/html-parsing.php';
require_once dirname(dirname(__FILE__)) . '/url.php'; # constructUrlFromRelativeLocation

use \MyPHPLibs\WebClient\HttpClient, \MyPHPLibs\WebClient\HttpResponse, \MyPHPLibs\URL;

class WebBrowser extends HttpClient {
  protected $lastResponse;

  public function get($url) {
    return $this->handleResponse(parent::get($url));
    /*
    $this->lastResponse = parent::get($url);
    return $this->lastResponse;
    */
  }

  public function post($url, $postValues, $extraHeaders = array()) {
    return $this->handleResponse(parent::post($url, $postValues, $extraHeaders));
  }

  private function handleResponse(HttpResponse $response) {
    $this->lastResponse = $response;
    # Check for meta-tag-based or JavaScript-based redirect...
    $redirectResponse = $this->doMetaOrJavascriptRedirectIfNeeded($response);
    return $redirectResponse ? $redirectResponse : $response;
  }

  private function doMetaOrJavascriptRedirectIfNeeded(HttpResponse $initialResponse) {
    $redirectTo = null;
    $haystack = trim(strtolower($initialResponse->content));
    $m = null;
    if (preg_match('/<meta http-equiv="refresh"\s+content="([0-9]+)\s*;\s*url=([^"]+)"[^>]*>/',
                   $haystack, $m)) {
      $secs = (int) $m[1];
      if ($secs > 0) {
        $this->warn("Ignoring the following meta-refresh tag because the time delay on it " .
                    "is greater than zero seconds: " . $m[0]);
      } else {
        $redirectTo = $this->makeAbsoluteUrl($m[2]);
        $this->notice("Performing redirect based on the following meta-refresh tag: " .
                      preg_replace('/[ \n]+/', " ", $m[0]));
      }
    } else if (preg_match('/<script>\s*document\.location\.href\s*=\s*"([^"]*)"\s*;\s*<\/script>/',
                          $haystack, $m)) {
      $redirectTo = $this->makeAbsoluteUrl($m[1]);
      $this->notice("Performing redirect based on the following JavaScript: " .
                    preg_replace('/[ \n]+/', " ", $m[0]));
    }
    if ($redirectTo) {
      $this->info("Redirecting to URL $redirectTo");
      return $this->get($redirectTo);
    } else {
      return null;
    }
  }

  public function findMatchingNodes($xpathExpr) {
    $xpathObj = new \DOMXPath(htmlSoupToDOMDocument($this->lastResponse->content, null));
    return findNodes($xpathObj, $xpathExpr);
  }

  protected function makeAbsoluteUrl($relativeLocation) {
    return URL\constructUrlFromRelativeLocation($this->currentLocation, $relativeLocation);
  }
}
