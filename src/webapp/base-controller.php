<?php

namespace SpareParts\Webapp;

require_once dirname(__FILE__) . '/../fs.php';            # pathJoin
require_once dirname(__FILE__) . '/../names.php';         # hyphenatedToCamelCase
require_once dirname(__FILE__) . '/../reflection.php';    # getNamesOfPublicMethods
require_once dirname(__FILE__) . '/current-request.php';  # getPath, isPostRequest, isGetRequest

use \Exception, \SpareParts\Webapp\CurrentRequest, \SpareParts\Names, \SpareParts\Reflection;

class Controller {

  # XXX: Should $user be 'protected' ??
  public $user;

  /** @var RequestContext */
  protected $context;

  /**
   * Override this method of all action-methods of the controller implementation need
   * to execute the same initialization code.
   */
  public function init() {}

  public function dispatch(RequestContext $context) {
    $this->context = $context;
    $this->user = $context->user;
    $action = $context->takeNextPathComponentOrNull();
    return $this->routeTo($action == null ? 'index' : $action, $context);
  }

  protected function routeTo($cmd, $context) {
    if (strtolower($cmd) != $cmd) {
      return $this->pageNotFound("Requested path has capital letters in it");
    }
    $method = Names\hyphenatedToCamelCase($cmd);
    $publicMethods = Reflection\getNamesOfPublicMethods($this);
    if ($method && in_array($method, $publicMethods) &&
        $method != 'init' && $method != 'dispatch') {
      $content = call_user_func(array($this, $method), $context);
    } else {
      return $this->pageNotFound("Controller " . get_class($this) . " has no method " .
                                 "named '$method'");
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
