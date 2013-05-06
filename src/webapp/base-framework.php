<?php

namespace MyPHPLibs\Webapp;

require_once dirname(__FILE__) . '/../fs.php';            # pathJoin
require_once dirname(__FILE__) . '/../types.php';         # asString, at
require_once dirname(__FILE__) . '/../string.php';        # endsWith
require_once dirname(__FILE__) . '/../utf8.php';          # hasInvalidUTF8Chars
require_once dirname(__FILE__) . '/../http.php';          # messageForStatusCode
require_once dirname(__FILE__) . '/../url.php';           # constructUrlFromRelativeLocation
require_once dirname(__FILE__) . '/current-request.php';  # isSecureHttpConnection

use \Exception, \MyPHPLibs\Webapp\CurrentRequest, \MyPHPLibs\URL;

abstract class FrontController {

  protected $webappDir, $actionsDir, $requestedPath, $cmd;
  private $requiredActions = array();

  function __construct($webappDir) {
    $this->webappDir = $webappDir;
    $this->actionsDir = pathJoin($this->webappDir, 'actions');
    if (!is_dir($this->actionsDir)) {
      throw new Exception("'actions' directory does not exist at expected " .
                          "location, {$this->actionsDir}");
    }
  }

  public function go() {

    $this->configureAndStartSession();

    $referrerInfo = "(referrer is " .
      (empty($_SERVER['HTTP_REFERER']) ? "unknown" : $_SERVER['HTTP_REFERER']) . ")";
    $this->info("Incoming HTTP" . (CurrentRequest\isSecureHttpConnection() ? "S" : "") .
      " request: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']} $referrerInfo");

    if ($_POST) {
      $this->info("Posted values: " . asString($_POST));
    }

    if (empty($_SERVER['HTTP_HOST'])) {
      // $this->warn("HTTP_HOST environment variable not set for request, so ...");
      throw new HttpProtocolError("No HTTP 'Host' header included in request " .
                                  "(i.e., \$_SERVER['HTTP_HOST'] was empty)");
    }

    $response = $this->handleRequest();
    $this->outputHttpResponse($response);

    $this->info("Served {$response->statusCode} response for path {$_SERVER['REQUEST_URI']} to " .
      "remote address {$_SERVER['REMOTE_ADDR']} $referrerInfo");
  }

  private function outputHttpResponse(HttpResponse $response) {
    header("HTTP/1.1 " . messageForStatusCode($response->statusCode));
    if ($response->contentType) {
      header('Content-Type: ' . $response->contentType);
    }
    // XXX: Add a Content-Length header, if one isn't set already ??
    //      Actually, it looks like PHP (or Apache or ?) is adding one for us...
    foreach ($response->headersSet() as $name) {
      foreach ($response->getValuesForHeader($name) as $value) {
        header($name . ': ' . $value);
      }
    }
    echo $response->content;
  }

  // XXX: Made public for the purposes of testing...  Should we factor this method
  //      into separate module or something??
  public function handleRequest() {
    $this->setCommandAndRequestedPath();
    $referrerInfo = " (referrer is " .
      (empty($_SERVER['HTTP_REFERER']) ? "unknown" : $_SERVER['HTTP_REFERER']) . ")";
    $reqMethod = $_SERVER['REQUEST_METHOD'];
    if (!in_array(strtolower($reqMethod), array('get', 'head', 'post'))) {
      $this->notice("Resource {$this->requestedPath} was requested using unsupported " .
                    "method $reqMethod");
      return $this->simpleTextResponse(405, 'Method Not Allowed');
    }
    $r = null;
    try {
      $r = $this->dispatch();
    } catch (DoRedirect $e) {
      $r = $this->redirectResponse($e->path, $e->statusCode, $referrerInfo);
    } catch (AccessForbidden $_) {
      // Just act like the resource doesn't exist...
      $r = $this->handlePageNotFound($referrerInfo);
    } catch (PageNotFound $_) {
      $r = $this->handlePageNotFound($referrerInfo);
    } catch (MaliciousRequestException $e) {
      $this->warn("Detected malicious request: " . $e->getMessage());
      $r = $this->simpleTextResponse(400, "go on, get");
    }
    if (strtolower($reqMethod) == 'head') $r->content = '';
    return $r;
  }

  protected function dispatch() {
    $this->checkRequestPathForExcessSlashes();
    $r = $this->dispatchToAction();
    if (empty($r)) throw new PageNotFound;
    return $r;
  }

  protected function checkRequestPathForExcessSlashes() {
    // XXX: This double-slash checking should only be done on the *path* portion of the URI;
    //      the query string should not be changed...
    $pathCleaned = preg_replace('@/{2,}@', '/', $_SERVER['REQUEST_URI']);
    if ($pathCleaned != $_SERVER['REQUEST_URI']) throw new DoRedirect($pathCleaned);
  }

  private function dispatchToAction() {
    $pathAndQuery = substr($_SERVER['REQUEST_URI'], 1);
    $origPath = urldecode(current(explode('?', $pathAndQuery)));
    $p = preg_replace('@/$@', '', $origPath);
    $unconsumedPathComponents = array();
    $actionsDir = $this->actionsDir;
    $defaultPath = pathJoin($actionsDir, $p . '.php');
    $indexPath = pathJoin($actionsDir, $p, 'index.php');
    while ($p != '' && $p != '.' && !file_exists($defaultPath) && !file_exists($indexPath)) {
      array_unshift($unconsumedPathComponents, basename($p));
      $p = dirname($p);
      $defaultPath = pathJoin($actionsDir, $p . '.php');
      $indexPath = pathJoin($actionsDir, $p, 'index.php');
    }
    if (file_exists($defaultPath) && file_exists($indexPath)) {
      throw new Exception("Found two files having the same route in actions directory: " .
                          "$defaultPath and $indexPath");
    }
    $actionPath = file_exists($defaultPath) ? $defaultPath : $indexPath;
    if (file_exists($actionPath)) {

      $pathComponents = explode('/', preg_replace('@/$@', '', $origPath));
      $user = $this->getUserForCurrentRequest();
      $this->checkAccessPrivileges($pathComponents, $user);
      $context = new RequestContext($unconsumedPathComponents, $user);

      // XXX: This bit is necessary for testing purposes -- so no classes will end up getting
      //      redefined. :o/
      $actionPath = realpath($actionPath);
      $funcOrClass = at($this->requiredActions, $actionPath) ?
        $this->requiredActions[$actionPath] : require $actionPath;
      $this->requiredActions[$actionPath] = $funcOrClass;

      // Do we need to add an extra slash to end of the URI, by redirecting?
      $requestedPath = current(explode('?', $_SERVER['REQUEST_URI']));
      if (!endsWith($requestedPath, '/')) {
        $routedTo = withoutSuffix(substr($actionPath, strlen($actionsDir)), '.php');
        if ((is_callable($funcOrClass) && basename($routedTo) == 'index') ||
            (is_string($funcOrClass) && class_exists($funcOrClass) &&
             ($routedTo == $requestedPath || $routedTo == "$requestedPath/index"))) {
          if (!in_array(strtolower($_SERVER['REQUEST_METHOD']), array('get', 'head'))) {
            throw new Exception("Attempting redirect for request other than GET or HEAD");
          }
          $q = at($_SERVER, 'QUERY_STRING');
          throw new DoRedirect($requestedPath . '/' . ($q ? ('?' . $q) : ''), 302);
        }
      }

      # TODO: Phase out this concept of a "default page".
      $page = method_exists($this, 'getDefaultPageForRequest') ?
        $this->getDefaultPageForRequest() : null;

      $result = null;
      if (is_callable($funcOrClass)) {
        $result = $funcOrClass($context);
      } else if (class_exists($funcOrClass)) {
        $controller = new $funcOrClass($page);
        if (method_exists($controller, 'init')) $controller->init();
        $result = $controller->dispatch($context);
        //if (!empty($content)) $page->body = $content;
      } else {
        throw new Exception("Action file '$actionPath' did not return a callable/function or " .
                            "a class name");
      }
      if ($result instanceof HttpResponse) {
        return $result;
      } else if ($result instanceof HtmlPage) {
        return $this->renderAndOutputPage($result);
      } else if (is_string($result) || empty($result)) {
        if ($page !== null) {
          if (!empty($result)) $page->body = $result;
          return $this->renderAndOutputPage($page);
        } else {
          return $this->simpleHtmlResponse(200, $result);
        }
      } else {
        throw new Exception("Expected action to return null, a string, an object of type " .
                            "HtmlPage, or an object of type HttpResponse, but got the " .
                            "following: " . asString($result));
      }
    } else {
      return null;
    }
  }

  /*
  protected function getDefaultPageForRequest() {
    $page = new HtmlPage;
    $page->currentLocation = $_SERVER['REQUEST_URI'];
    $page->contentType = 'text/html; charset=utf-8';
    return $page;
  }
  */

  protected function handlePageNotFound($referrerInfo) {
    $relocateTo = null;
    # If the URI has illegal character codes, don't even bother with it...
    if (!\hasInvalidUTF8Chars($_SERVER['REQUEST_URI'])) {
      $relocateTo = $this->checkForSpaceAtEndOfRequestedPath();
      if ($relocateTo === null) $relocateTo = $this->checkForRelocatedResource();
      if ($relocateTo === null) $relocateTo = $this->checkForRedirectDueToExtraCrapOnURI();
    }
    if ($relocateTo) {
      return $this->redirectResponse($relocateTo, 301, $referrerInfo);
    } else {
      $this->warn("Someone tried to access non-existent page at URI {$_SERVER['REQUEST_URI']}" .
                  $referrerInfo);
      return $this->get404Response();
    }
  }

  protected function get404Response() {
    return $this->simpleTextResponse(404, "Sorry, we've got none of that.");
  }

  protected function simpleTextResponse($code, $content) {
    $response = new HttpResponse;
    $response->statusCode = $code;
    $response->contentType = 'text/plain';
    $response->content = $content;
    return $response;
  }

  protected function simpleHtmlResponse($code, $content) {
    $response = new HttpResponse;
    $response->statusCode = $code;
    $response->contentType = 'text/html; charset=utf-8';
    $response->content = $content;
    return $response;
  }

  private function checkForRedirectDueToExtraCrapOnURI() {
    $uri = $_SERVER['REQUEST_URI'];
    if (strstr($uri, '#')) return current(explode('#', $uri));
    $crap = array('%20', '%22', '%3e', '%5d', '&quot;', ')', ']', '.', ',', '_', '"', "'");
    foreach ($crap as $c) {
      if (endsWith($uri, $c)) return substr($uri, 0, (0 - strlen($c)));
    }
    return null;
  }

  protected function redirectResponse($path, $statusCode, $referrerInfo) {
    $currentUrl = CurrentRequest\getURL();
    $this->info("Redirecting from " . $currentUrl . " to location " . $path . $referrerInfo);
    $url = URL\constructUrlFromRelativeLocation($currentUrl, $path);
    $r = new HttpResponse;
    $r->statusCode = $statusCode;
    $r->addHeader('Location', $url);
    return $r;
  }

  protected function configureAndStartSession() {
    $sessionName = $this->nameOfSessionCookie();
    $sessionLifetime = $this->sessionLifetimeInSeconds();
    $cookieDomain = $this->cookieDomain();

    if ($cookieDomain !== null) {
      if (strpos($_SERVER['HTTP_HOST'], '.') === false) {
        throw new Exception("No 'cookieDomain' should be used when the hostname " .
          "({$_SERVER['HTTP_HOST']}) does not contain a dot (.), as setting cookies " .
          "seems to fail in some/most browsers when specifying such a domain");
      }
    }

    ini_set('session.name', $sessionName);
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_domain', $cookieDomain);
    ini_set('session.gc_maxlifetime', $sessionLifetime);
    ini_set('session.cookie_lifetime', $sessionLifetime);
    ini_set('session.cookie_secure', 0);
    ini_set('session.cookie_httponly', 0);

    if (!isset($_COOKIE[$sessionName])) {
      $this->info('Beginning new session for client using ' .
           (isset($_SERVER['HTTP_USER_AGENT']) ?
             ('the following User-Agent: ' . $_SERVER['HTTP_USER_AGENT']) : 'unknown User-Agent'));
    } else if (!preg_match('/^[-,a-zA-Z0-9]+$/', $_COOKIE[$sessionName])) {
      warn("Illegal session-ID provided: " . $_COOKIE[$sessionName]);
      unset($_COOKIE[$sessionName]);
    }

    session_start();

    # Reset the expiration time everytime the user hits our site.
    if (isset($_COOKIE[$sessionName])) {
      setcookie($sessionName, $_COOKIE[$sessionName], time() + $sessionLifetime,
                '/', $cookieDomain);
    }
  }

  private function checkForSpaceAtEndOfRequestedPath() {
    $unescaped = urldecode($this->requestedPath);
    $trimmed = rtrim($unescaped);
    return ($trimmed != $unescaped) ? $trimmed : null;
  }

  protected function checkForRelocatedResource() { return null; }

  private function setCommandAndRequestedPath() {
    # Strip "/index.php" if it prefixes the URI, and remove any slashes from
    # the beginning and/or end of the URI; then, split on slashes to create an
    # array of the URI components.
    $parts = explode('?', $_SERVER['REQUEST_URI']);
    $origPath = $parts[0];
    $this->requestedPath = preg_replace('@\/index\.php[\/]*@i', '', $origPath);
    $this->cmd = explode('/', preg_replace('@^\/@', '', $this->requestedPath));
  }

  protected function getStaticPages() {
    $staticPaths = array();
    foreach (getFilesInDir(pathJoin(WEBAPP_DIR, 'templates', 'static')) as $fname) {
      $staticPaths[] = substr($fname, 0, -4);
    }
    return $staticPaths;
  }

  protected function getUserForCurrentRequest() { return null; }
  abstract protected function checkAccessPrivileges($cmd, $user);
  abstract protected function renderAndOutputPage($page);

  protected function nameOfSessionCookie() { return 'sessionid'; }
  protected function cookieDomain() { return null; }
  abstract protected function sessionLifetimeInSeconds();
  abstract protected function info($msg);
  abstract protected function notice($msg);
  abstract protected function warn($msg);
}

class RequestContext {
  private $unconsumedPathComponents;
  public $user;

  function __construct($unconsumedPathComponents, $user) {
    $this->unconsumedPathComponents = $unconsumedPathComponents;
    $this->user = $user;
  }

  public function takeNextPathComponent() {
    $c = array_shift($this->unconsumedPathComponents);
    return $c;
  }
}

class HttpResponse {
  public $statusCode, $contentType, $content;
  private $headers = array();
  public function addHeader($name, $value) {
    if (empty($this->headers[$name])) $this->headers[$name] = array();
    $this->headers[$name] []= $value;
  }
  public function headersSet() { return array_keys($this->headers); }
  public function getValuesForHeader($name) { return $this->headers[$name]; }
}

class DoRedirect extends Exception {
  public $path;
  function __construct($path, $code = 302) {
    $this->path = $path;
    $this->statusCode = $code;
  }
}

class PageNotFound extends Exception {}
class AccessForbidden extends Exception {}
class HttpProtocolError extends Exception {}
class MaliciousRequestException extends Exception {}

class HtmlPage {
  public $contentType = 'text/html', $body = '';
}
