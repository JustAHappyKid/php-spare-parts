<?php

namespace SpareParts\Webapp;

require_once dirname(__FILE__) . '/../fs.php';            # pathJoin
require_once dirname(__FILE__) . '/../names.php';         # hyphenatedToCamelCaseName
require_once dirname(__FILE__) . '/current-request.php';  # getPath, isPostRequest, isGetRequest

use \SpareParts\Webapp\CurrentRequest;

class Controller {
  public $user;

  function dispatch($context) {
    $this->context = $context;
    $this->user = $context->user;
    return $this->routeTo($context->takeNextPathComponent(), $context);
  }

  protected function routeTo($cmd, $context) {
    $method = hyphenatedToCamelCaseName($cmd);
    $content = '';
    if ($method && method_exists($this, $method) && $method != 'init') {
      $content = call_user_func(array($this, $method), $context);
    } else if (empty($method) && method_exists($this, 'index')) {
      $content = call_user_func(array($this, 'index'), $context);
    } else {
      $this->pageNotFound("Controller " . get_class($this) . " has no method named '$method'");
    }
    return $content;
  }

  protected function getCurrentPath() {
    return CurrentRequest\getPath();
  }

  protected function isPostRequest() {
    return CurrentRequest\isPostRequest();
  }

  protected function isGetRequest() {
    return CurrentRequest\isGetRequest();
  }

  protected function saveInSession($var, $value) {
    $_SESSION[$var] = $value;
  }

  protected function getFromSession($var, $default = null) {
    return isset($_SESSION[$var]) ? $_SESSION[$var] : $default;
  }

  protected function takeFromSession($var, $default = null) {
    $value = $this->getFromSession($var, $default);
    unset($_SESSION[$var]);
    return $value;
  }

  protected function redirect($path, $code = 302) {
    $host = $_SERVER['HTTP_HOST'];
    if (substr($path, 0, strlen($host) == $host)) {
      throw new Exception("redirect should be called with a relative or absolute path, " .
        "without the HTTP_HOST as a prefix");
    }
    throw new DoRedirect($path, $code);
  }

  protected function pageNotFound($msg = null) {
    throw new PageNotFound($msg);
  }
}
