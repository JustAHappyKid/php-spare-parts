<?php

namespace MyPHPLibs\Webapp;

require_once dirname(__FILE__) . '/../fs.php';            # pathJoin
require_once dirname(__FILE__) . '/../types.php';         # asString, at
require_once dirname(__FILE__) . '/../string.php';        # endsWith
require_once dirname(__FILE__) . '/../utf8.php';          # hasInvalidUTF8Chars
require_once dirname(__FILE__) . '/../http.php';          # messageForStatusCode
require_once dirname(__FILE__) . '/../url.php';           # constructUrlFromRelativeLocation
require_once dirname(__FILE__) . '/current-request.php';  # isSecureHttpConnection

use \Exception, \MyPHPLibs\Webapp\CurrentRequest;

abstract class FrontController {

  protected $webappDir, $requestedPath, $cmd;
  private $requiredActions = array();

  function __construct($webappDir) {
    $this->webappDir = $webappDir;
  }

  public function go() {

    $this->configureAndStartSession();

    $referrerInfo = " (referrer is " .
      (empty($_SERVER['HTTP_REFERER']) ? "unknown" : $_SERVER['HTTP_REFERER']) . ")";
    $this->info("Incoming HTTP" . (CurrentRequest\isSecureHttpConnection() ? "S" : "") .
      " request: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']} $referrerInfo");

    if ($_POST) {
      $this->info("Posted values: " . asString($_POST));
    }

    if (empty($_SERVER['HTTP_HOST'])) {
      $redirectTo = 'http://' . DOMAIN . '/';
      $this->warn("HTTP_HOST environment variable not set for request, so " .
                  "redirecting to $redirectTo");
      header("Location: $redirectTo");
      return;
    }

    $response = $this->handleRequest();
    $this->outputHttpResponse($response);

    $this->info("Served {$response->statusCode} response for path {$_SERVER['REQUEST_URI']} to " .
         "remote address {$_SERVER['REMOTE_ADDR']} $referrerInfo");
  }

  private function outputHttpResponse($response) {
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
      $r = new ResponseObj;
      $r->statusCode = 405; # "Method Not Allowed"
      $r->content = '405, Method Not Allowed';
      return $r;
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
    //$fname = substr($_SERVER['REQUEST_URI'], 1) . '.php';
    $pathAndQuery = substr($_SERVER['REQUEST_URI'], 1);
    $origPath = urldecode(current(explode('?', $pathAndQuery)));
    $p = preg_replace('@/$@', '', $origPath);
    $unconsumedPathComponents = array();
    $actionsDir = pathJoin($this->webappDir, 'actions');
    $actionPath = pathJoin($actionsDir, $p . '.php');
    while ($p != '' && $p != '.' && !file_exists($actionPath)) {
      array_unshift($unconsumedPathComponents, basename($p));
      $p = dirname($p);
      $actionPath = pathJoin($actionsDir, $p . '.php');
    }
    if (!file_exists($actionPath)) $actionPath = pathJoin($actionsDir, $origPath, 'index.php');
    if (file_exists($actionPath)) {

      $pathComponents = explode('/', preg_replace('@/$@', '', $origPath));
      $user = $this->getUserForCurrentRequest();
      $this->checkAccessPrivileges($pathComponents, $user);
      $context = new RequestContext($unconsumedPathComponents, $user);

      // XXX: This bit is necessary for testing purposes -- so no classes will end up getting
      //      redefined. :o/
      $funcOrClass = at($this->requiredActions, $actionPath) ?
        $this->requiredActions[$actionPath] : require $actionPath;
      $this->requiredActions[$actionPath] = $funcOrClass;

      // Do we need to add an extra slash to end of the URI, by redirecting?
      $requestedPath = current(explode('?', $_SERVER['REQUEST_URI']));
      if (!endsWith($requestedPath, '/')) {
        $routedTo = withoutSuffix(substr($actionPath, strlen($actionsDir)), '.php');
        //if ((is_callable($funcOrClass) && $routedTo != $requestedPath) ||
        if ((is_callable($funcOrClass) && basename($routedTo) == 'index') ||
            (is_string($funcOrClass) && class_exists($funcOrClass) &&
             $routedTo == $requestedPath)) {
          if (!in_array(strtolower($_SERVER['REQUEST_METHOD']), array('get', 'head'))) {
            throw new Exception("Attempting redirect for request other than GET or HEAD");
          }
          throw new DoRedirect($requestedPath . '/' . at($_SERVER, 'QUERY_STRING', ''), 302);
        }
      }

      $page = $this->getDefaultPageForRequest();
      if (is_callable($funcOrClass)) {
        $r = $funcOrClass($context);
        if ($r instanceof HtmlPage) {
          $page = $r;
        } else if (is_string($r)) {
          $page->body = $r;
        } else {
          throw new Exception("Action gave result not of type HtmlPage nor string");
        }
      } else if (class_exists($funcOrClass)) {
        $controller = new $funcOrClass($page);
        //$controller->systemEmailAddr = $this->getSystemEmailAddr();
        if (method_exists($controller, 'init')) $controller->init();
        $content = $controller->dispatch($context);
        if (!empty($content)) $page->body = $content;
      } else {
        throw new Exception("Action file '$actionPath' did not return a callable/function or " .
                            "a class name");
      }
      return $this->renderAndOutputPage($page);
    } else {
      return null;
    }
  }

/*
  protected function renderAndOutputPage($page) {

    $response = new ResponseObj;
    $response->statusCode = 200;

    // XXX: Is this right???
    $response->contentType = $page->contentType;
    
    if ($page->layout) {
      $smarty = createSmartyInstance();
      $smarty->assign('page', $page);
      $smarty->assign('successfulLogin', at($_SESSION, 'successfulLogin'));
      unset($_SESSION['successfulLogin']);
      $response->content = $smarty->fetch($page->layout);
    } else {
      $response->content = $page->body;
    }

    return $response;
  }
*/

  protected function getDefaultPageForRequest() {
    $page = new HtmlPage;
    $page->currentLocation = $_SERVER['REQUEST_URI'];
    $page->contentType = 'text/html; charset=utf-8';
    return $page;
  }

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
    $response = new ResponseObj;
    $response->statusCode = 404;
    $response->contentType = 'text/html';
    $response->content = "<html> <body> <p>Sorry, we've got none of that.</p> </body> </html>";
    return $response;
  }

  /*
  protected function getBasePageObject($cls = null) {
    $page = $cls ? new $cls : new HtmlPage;
    $page->currentLocation = $_SERVER['REQUEST_URI'];
    $page->contentType = 'text/html; charset=utf-8';
    $page->currentYear = strftime('%Y');
    return $page;
  }
  */

  private function checkForRedirectDueToExtraCrapOnURI() {
    $uri = $_SERVER['REQUEST_URI'];
    if (strstr($uri, '#')) return current(explode('#', $uri));
    $crap = array('%20', '%22', '%3e', '&quot;', ')', '.', ',', '_', '"', "'");
    foreach ($crap as $c) {
      if (endsWith($uri, $c)) return substr($uri, 0, (0 - strlen($c)));
    }
    return null;
  }

  protected function redirectResponse($path, $statusCode, $referrerInfo) {
    $currentUrl = CurrentRequest\getURL();
    $this->info("Redirecting from " . $currentUrl . " to location " . $path . $referrerInfo);
    $url = \constructUrlFromRelativeLocation($currentUrl, $path);
    $r = new ResponseObj;
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

  private function getControllerName($controllersDir, $pathParts) {
    $name = null;
    if ($this->requestedPath == '/') {
      $name = 'FrontPage';
      require_once pathJoin($controllersDir, 'front-page.php');
    } else {
      $name = $pathParts[0];
      if (is_file(pathJoin($controllersDir, $name . '.php'))) {
        require_once pathJoin($controllersDir, $name . '.php');
      } else {
        $name = null;
      }
    }
    return $name;
  }

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

class ResponseObj {
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
class MaliciousRequestException extends Exception {}

class HtmlPage {
  public $contentType = 'text/html', $body = '';
}
