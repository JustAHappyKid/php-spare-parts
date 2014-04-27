<?php

namespace SpareParts\Webapp;

require_once dirname(__FILE__) . '/../fs.php';            # pathJoin
require_once dirname(__FILE__) . '/../types.php';         # asString, at
require_once dirname(__FILE__) . '/../reflection.php';    # getClassesDefinedInFile
require_once dirname(__FILE__) . '/../string.php';        # endsWith
require_once dirname(__FILE__) . '/../utf8.php';          # hasInvalidUTF8Chars
require_once dirname(__FILE__) . '/../http.php';          # messageForStatusCode
require_once dirname(__FILE__) . '/../url.php';           # constructUrlFromRelativeLocation
require_once dirname(__FILE__) . '/current-request.php';  # isSecureHttpConnection
require_once dirname(__FILE__) . '/http-response.php';    # HttpResponse
require_once dirname(__FILE__) . '/filters/interface.php';# Filter

use \Exception, \SpareParts\Webapp\CurrentRequest, \SpareParts\URL, \SpareParts\HTTP,
  \SpareParts\Reflection;

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
    foreach ($this->filters() as $f) {
      if (!($f instanceof Filter))
        throw new Exception("Found " . Reflection\identifyClassOrType($f) . ", which does " .
                            "not implement the Filter interface");
    }
  }

  /**
   * Filters. Provide an array of Filter implementations, each of which will be invoked
   * for each incoming request and respective outgoing response. For incoming requests
   * the filters will be invoked (via the 'incoming' method) in the order in which they
   * appear in the array; for outgoing requests, they'll be invoked (via the 'outgoing'
   * method) in reverse order.
   * @return Filter[]
   */
  protected function filters() { return array(); }

  /**
   * Logging functions. It's recommended you override these methods, routing the provided $msg,
   * in each case, to your logging mechanism of choice.
   */
  protected function info  ($msg) {}
  protected function notice($msg) {}
  protected function warn  ($msg) {}

  /**
   * Access-control and user session support.
   */
  protected function getUserForCurrentRequest() { return null; }
  protected function checkAccessPrivileges($cmd, $user) { return true; }
  protected function nameOfSessionCookie() { return 'sessionid'; }
  protected function sessionLifetimeInSeconds() { return 60 * 60 * 24; /* one day */ }

  /**
   * If you run your website off of more than one sub-domain (e.g., a.my-site.com and
   * b.my-site.com) and those two domains need to have access to the same cookies,
   * override this method appropriately.
   */
  protected function cookieDomain() { return null; }

  public function go() {

    $this->configureAndStartSession();

    $referrerInfo = "(referrer is " .
      (empty($_SERVER['HTTP_REFERER']) ? "unknown" : $_SERVER['HTTP_REFERER']) . ")";
    $this->info("Incoming HTTP" . (CurrentRequest\isSecureHttpConnection() ? "S" : "") .
      " request: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']} $referrerInfo");

    if ($_POST) {
      $this->info("Posted values: " . asString($_POST));
    }

    $response = $this->handleRequest();
    $this->outputHttpResponse($response);

    $this->info("Served {$response->statusCode} response for path {$_SERVER['REQUEST_URI']} to " .
      "remote address {$_SERVER['REMOTE_ADDR']} $referrerInfo");
  }

  protected function outputHttpResponse(HttpResponse $response) {
    header("HTTP/1.1 " . HTTP\messageForStatusCode($response->statusCode));
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

  private function handleRequest() {
    if (empty($_SERVER['HTTP_HOST'])) {
      return $this->simpleTextResponse(400, "No 'Host' header given");
    }
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

      # XXX: Make this a filter?
      $this->checkForMaliciousContent();

      foreach ($this->filters() as $f) $f->incoming();

      $r = $this->dispatch();

      /** @var Filter[] $filtersReversed */
      $filtersReversed = array_reverse($this->filters());
      foreach ($filtersReversed as $f) $f->outgoing($r);

    } catch (DoRedirect $e) {
      $r = $this->redirectResponse($e->path, $e->statusCode, $referrerInfo);
    } catch (AccessForbidden $_) {
      // Just act like the resource doesn't exist...
      $r = $this->handlePageNotFound($referrerInfo);
    } catch (PageNotFound $_) {
      $r = $this->handlePageNotFound($referrerInfo);
    } catch (MaliciousRequestException $e) {
      $this->warn("Detected malicious request: " . $e->getMessage());
      $r = $this->handleMaliciousRequest($e);
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
            $this->warn("Performing redirect for {$_SERVER['REQUEST_METHOD']} request");
          }
          $q = at($_SERVER, 'QUERY_STRING');
          throw new DoRedirect($requestedPath . '/' . ($q ? ('?' . $q) : ''), 302);
        }
      }

      $result = null;
      if (is_callable($funcOrClass)) {
        $result = $this->invokeAction($funcOrClass, $context);
      } else if (class_exists($funcOrClass)) {
        $result = $this->invokeController($funcOrClass, $context);
      } else {
        $controllers = array_filter(Reflection\getClassesDefinedInFile($actionPath),
          function($cls) { return is_subclass_of($cls, 'SpareParts\\Webapp\\Controller'); });
        if (count($controllers) == 1) {
          $result = $this->invokeController(current($controllers), $context);
        } else if (count($controllers) > 1) {
          throw new RoutingException("File '$actionPath' contained multiple classes " .
            "implementing SpareParts\\Webapp\\Controller");
        } else {
          throw new RoutingException("Action file '$actionPath' did not return a callable/" .
            "function, nor did it provide a class implementing SpareParts\\Webapp\\Controller");
        }
      }
      if ($result instanceof HttpResponse) {
        return $result;
      } else if ($result instanceof Renderable) {
        return $result->toHttpResponse();
      } else if (is_string($result)) {
        return $this->simpleHtmlResponse(200, $result);
      } else if (empty($result)) {
        throw new Exception("Action returned empty/null response");
      } else {
        throw new Exception("Expected action to return null, a string, an object of type " .
                            "HtmlPage, or an object of type HttpResponse, but got the " .
                            "following: " . asString($result));
      }
    } else {
      return null;
    }
  }

  protected function invokeAction($funcOrClass, RequestContext $context) {
    return $funcOrClass($context);
  }

  protected function invokeController($className, RequestContext $context) {
    $controller = $this->getControllerByName($className);
    $controller->init();
    return $controller->dispatch($context);
  }

  /**
   * Override this method if, for example, you want to pass custom data to your controllers
   * (such as request data or a default page-layout object or what have you).
   * @param string $className Fully-qualified name for class (that extends from Controller)
   * @return Controller
   */
  protected function getControllerByName($className) {
    return new $className();
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
    return $this->simpleTextResponse(404, "Sorry, we've got none of that.");
  }

  protected function handleMaliciousRequest(MaliciousRequestException $e) {
    $this->warn("Request appears to be malicious");
    return $this->simpleTextResponse(400, "go on, get");
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

  protected function checkForMaliciousContent() {
    $varsToCheck = array('POST' => $_POST, 'GET' => $_GET, 'COOKIE' => $_COOKIE);
    foreach ($varsToCheck as $baseName => $base) {
      if (empty($base)) $base = array();
      $this->checkForMaliciousContentInArray($base, $baseName);
    }
    foreach ($_COOKIE as $var => $val) {
      if (is_array($val)) {
        throw new MaliciousRequestException(
          "Found array in \$_COOKIE content at index '$var': " . asString($val));
      }
    }
  }

  private function checkForMaliciousContentInArray(Array $a, $arrayName) {
    $suspectContent = array("/passwd", "sleep(", "../", "%00");
    foreach ($a as $var => $val) {
      if (is_array($val)) {
        $this->checkForMaliciousContentInArray($val, $arrayName);
      } else {
        foreach ($suspectContent as $suspect) {
          if (contains(strtolower($val), $suspect))
            throw new MaliciousRequestException(
              "Found suspect content in \$_$arrayName at index '$var': $val");
        }
      }
      foreach ($suspectContent as $suspect) {
        if (contains(strtolower($var), $suspect))
          throw new MaliciousRequestException(
            "Found suspect content in key of \$_$arrayName: $var");
      }
    }
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
      $this->warn("Illegal session-ID provided: " . $_COOKIE[$sessionName]);
      unset($_COOKIE[$sessionName]);
    }

    $this->sessionStart();

    # Reset the expiration time every-time the user hits our site.
    if (isset($_COOKIE[$sessionName])) {
      setcookie($sessionName, $_COOKIE[$sessionName], time() + $sessionLifetime,
                '/', $cookieDomain);
    }
  }

  protected function sessionStart() { session_start(); }

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
}

class RequestContext {
  private $unconsumedPathComponents;
  public $user;

  function __construct($unconsumedPathComponents, $user) {
    $this->unconsumedPathComponents = $unconsumedPathComponents;
    $this->user = $user;
  }

  public function takeNextPathComponentOrNull() {
    return array_shift($this->unconsumedPathComponents);
  }

  public function takeNextPathComponent() {
    if (count($this->unconsumedPathComponents) == 0)
      throw new NoMorePathComponents("Expected additional path component in URI");
    return $this->takeNextPathComponentOrNull();
  }
}

class RoutingException extends Exception {}

class DoRedirect extends Exception {
  public $path;
  function __construct($path, $code = 302) {
    $this->path = $path;
    $this->statusCode = $code;
  }
}

class PageNotFound extends Exception {}
class NoMorePathComponents extends PageNotFound {}
class AccessForbidden extends Exception {}
class MaliciousRequestException extends Exception {}

abstract class Renderable {
  abstract public function render();
  public function statusCode() { return 200; }
  public function contentType() { return "text/html"; }
  public function toHttpResponse() {
    return new HttpResponse($this->statusCode(), $this->contentType(), $this->render());
  }
}
