<?php

/**
 * Here you'll find a "test harness" (class for housing individual unit tests/functions) that
 * allows for testing of a webapp that uses the php-spare-parts
 * "mini" web-framework.
 */

namespace SpareParts\Test;

require_once dirname(__FILE__) . '/base-framework.php';                 # TestHarness, TestFailure
require_once dirname(__FILE__) . '/assertions.php';                     # assertEqual, ...
require_once dirname(dirname(__FILE__)) . '/web-client/html-form.php';  # HtmlForm
require_once dirname(dirname(__FILE__)) . '/url.php';                   # makeUrlQuery, ...

use \BadMethodCallException, \InvalidArgumentException, \UnexpectedValueException,
  \DOMDocument, \DOMNode, \DOMElement, \SpareParts\URL, \SpareParts\WebClient,
  \SpareParts\WebClient\HtmlForm, \SpareParts\Webapp\HttpResponse;

abstract class WebappTestingHarness extends TestHarness {

  private $xpathObj;

  /**
   * When this method is called (via the below makeRequest) method, all relevant PHP
   * environment variables should be set (XXX: it's likely we're missing a few things
   * yet -- feel free to report as bugs!) so as to "mock" the request which the underlying
   * test-case is intending to test. For example, given a test-case that invoked the 'get'
   * method as follows...
   *   $this->get('/path/to-something?q=abc123')
   * ...by the time 'dispatchToWebapp' were invoked, $_GET would equal array('q' => 'abc123'),
   * $_SERVER['REQUEST_URI'] would equal '/path/to-something?q=abc123' and so on.
   */
  abstract protected function dispatchToWebapp();

  /**
   * The implementation of this method must return list/array of error messages (generated when
   * validating a form, for example). Each error can be a simple string, or could be an object,
   * if you wanted to get more sophisticated. The simplest implementation of this method might
   * be nothing more than the following:
   *   return $this->xpathQuery("//*[@class='error']");
   */
  abstract protected function getValidationErrors();

  /**
   * This is the domain on which the webapp under test will appear to be hosted. I.e., the
   * $_SERVER['HTTP_HOST'] slot will hold the value returned here.
   */
  protected function domain() { return 'test.net'; }

  protected $lastResponse;
  private $currentPath, $followRedirects = false;

  function setUp() {
    $_SESSION = array();
    $this->lastResponse = null;
    $this->followRedirects = false;
  }

  protected function clearSession() { $_SESSION = array(); }

  protected function followRedirects($follow = true) { $this->followRedirects = $follow; }

  protected function getCurrentPath() {
    return $this->currentPath;
  }

  protected function get($path, $queryVars = null, $serverVars = null) {
    if ($queryVars !== null && strstr($path, '?') !== false) {
      throw new InvalidArgumentException(
        "Query string given in \$path and \$queryVars was specified; " .
        "please use one or the other for specifying your 'GET' parameters");
    }
    $_GET = $queryVars ? $queryVars : array();
    $_POST = null;
    return $this->makeRequest('GET', $path . ($queryVars ? URL\makeUrlQuery($queryVars) : ''),
                              $serverVars);
  }

  protected function post($path, $vars = array(), $serverVars = null) {
    $_GET = array();
    $_POST = $vars;
    return $this->makeRequest('POST', $path, $serverVars);
  }

  protected function getForm($formId = null) {
    $theForm = null;
    $forms = $this->xpathQuery("//form" . ($formId ? "[@id='$formId']" : ""));
    if (count($forms) == 0) {
      throw new NoSuchForm("No forms found " . ($formId ? "with ID '$formId'" : "on page"));
    } else if (count($forms) > 1) {
      return fail("Multiple forms found " . ($formId ? "with ID '$formId'" : "on page"));
    } else {
      $theForm = current($forms);
      return HtmlForm::fromDOMNode($theForm, $this->xpathObj);
    }
  }

  protected function submitForm(HtmlForm $form, Array $nonDefaultValues = array(),
                                $submitButton = null) {
    $this->justSubmitForm($form, $nonDefaultValues, $submitButton);
    $errors = $this->getValidationErrors();
    if (count($errors) > 0) {
      $renderError = function($e) {
        if ($e instanceof DOMNode) {
          return $e->textContent;
        } else if (method_exists($e, 'toString')) {
          return $e->toString();
        } else {
          throw new InvalidArgumentException("Could not render given error object");
        }
      };
      $errorsStr = implode(', ', array_map($renderError, $errors));
      throw new ValidationErrors("Got validation error(s) when submitting form: " . $errorsStr);
    }
  }

  protected function submitFormExpectingErrors($form, $nonDefaultValues, $submitButton = null) {
    $this->justSubmitForm($form, $nonDefaultValues, $submitButton);
    \assertTrue(count($this->getValidationErrors()) > 0,
                "Expected to get validation error(s) from submitting form");
  }

  private function justSubmitForm(HtmlForm $form, $nonDefaultValues, $submitButton = null) {
    if (!$form->hasSubmitButton()) {
      fail("Form has no submit button");
    }
    $m = $form->method;
    \assertTrue($m == 'post' || $m == 'get');
    $fieldNames = array();
    foreach ($form->fields as $n => $f) {
      $matches = null;
      if (preg_match('/^([^\[\]]+)\[([^\[\]])+\]$/', $n, $matches)) {
        $fieldNames []= $matches[1];
      } else {
        $fieldNames []= $n;
      }
    }
    foreach ($nonDefaultValues as $name => $_) {
      if (!in_array($name, $fieldNames)) fail("Form has no field named '$name'");
    }
    $values = array_merge($form->getDefaultValuesToSubmit($submitButton), $nonDefaultValues);
    $uri = null;
    // XXX: What to do if $form->action is the empty string?? What do 'real' browsers do?
    if ($form->action === null) {
      // XXX: What if the form came from a page other than the current one?!!
      $uri = $this->getCurrentPath();
    } else {
      $parts = parse_url($form->action);
      $uri = $parts['path'] .
        (isset($parts['query']) ? ('?' . $parts['query']) : '') .
        (isset($parts['fragment']) ? ('#' . $parts['fragment']) : '');
    }
    // XXX: Should we always assume HTTP here (as opposed to HTTPS)?
    $referer = 'http://' . $this->domain() . $this->getCurrentPath();
    $this->$m($uri, $values, array('HTTP_REFERER' => $referer));
  }

  protected function clickLink($xpathToLink) {
    $dom = new DOMDocument();
    $dom->loadHTML($this->currentPageContent());
    $as = $this->findElements($xpathToLink);
    if (count($as) == 0) {
      fail("Could not find link matching XPath expression: $xpathToLink");
    }
    $href = $as[0]->getAttribute('href');
    $path = $href;
    $hrefLower = strtolower($href);
    if (beginsWith($hrefLower, 'http:') || beginsWith($hrefLower, 'https:')) {
      $d = URL\takeDomain($hrefLower);
      if ($d != strtolower($this->domain())) {
        throw new InvalidArgumentException("Cannot follow link for domain $d as it's " .
          "not the domain under test ({$this->domain()})");
      }
      $path = URL\takePathAndQuery($href);
    }
    $this->get($path);
  }

  /**
   * Like xpathQuery but will raise an exception if a non-element node is found for
   * the given query/expression.
   * @param string $expression XPath expression to query on
   * @throws UnexpectedValueException
   * @return DOMElement[]
   */
  protected function findElements($expression) {
    $es = $this->xpathQuery($expression);
    foreach ($es as $e) {
      if (!($e instanceof DOMElement))
        throw new \UnexpectedValueException("Expected to find only DOMElement nodes for XPath " .
          "expression $expression but got node of type " . get_class($e));
    }
    return $es;
  }

  /**
   * Return all nodes in content of current page that match the given XPath expression.
   * @param string $expression XPath expression to query on
   * @return DOMNode[]
   */
  protected function xpathQuery($expression) {
    $this->xpathObj = WebClient\htmlSoupToXPathObject($this->currentPageContent());
    $r = $this->xpathObj->evaluate($expression);
    $elems = array();
    if (empty($r)) fail("No matches found for XPath expression: $expression");
    for ($i = 0; $i < $r->length; ++$i) {
      $elems[] = $r->item($i);
    }
    return $elems;
  }

  protected function assertContains($xpath) {
    \assertTrue(count($this->xpathQuery($xpath)) > 0,
      "Expected to find an element matching following XPath expression: $xpath");
  }

  protected function assertDoesNotContain($xpath) {
    \assertTrue(count($this->xpathQuery($xpath)) == 0,
      "Did not expect to find an element matching following XPath expression: $xpath");
  }

  protected function currentPageContent() {
    if (empty($this->lastResponse)) {
      throw new BadMethodCallException("Attempt to access current page's content " .
                                       "without first making request");
    }
    return $this->lastResponse->content;
  }

  protected function makeRequest($method, $pathAndQuery, $serverVars = null,
                                 $numRedirectsToFollow = self::maxRedirects) {
    $defaultServerVars = array('REQUEST_METHOD' => $method, 'REMOTE_ADDR' => '99.99.99.99',
      'HTTP_USER_AGENT' => 'Mozillar Farfox', 'HTTP_HOST' => $this->domain(),
      'SERVER_NAME' => $this->domain(), 'REQUEST_URI' => $pathAndQuery);
    $_GET = URL\readQueryFromURI($pathAndQuery);
    $_SERVER = $serverVars ? array_merge($defaultServerVars, $serverVars) : $defaultServerVars;
    $pathAndQuerySplit = explode('?', $pathAndQuery);
    if (count($pathAndQuerySplit) > 2) {
      throw new InvalidArgumentException('Given URI has multiple question marks present: ' .
                                         $pathAndQuery);
    }
    $this->currentPath = $pathAndQuerySplit[0];
    if (count($pathAndQuerySplit) > 1) $_SERVER['QUERY_STRING'] = $pathAndQuerySplit[1];
    $this->lastResponse = $this->dispatchToWebapp();
    if (empty($this->lastResponse) || !($this->lastResponse instanceof HttpResponse)) {
      fail("'dispatchToWebapp' did not return an HttpResponse instance!");
    }
    if ($this->lastResponse->statusCode == 200) {
      return $this->lastResponse;
    } else if (in_array($this->lastResponse->statusCode, array(301, 302, 303))) {
      $locValues = $this->lastResponse->getValuesForHeader('Location');
      \assertEqual(1, count($locValues));
      $parts = parse_url(current($locValues));
      \assertEqual($this->domain(), $parts['host']);
      $redirectTo = $parts['path'] . (isset($parts['query']) ? ('?' . $parts['query']) : '');
      if ($this->followRedirects) {
        if ($numRedirectsToFollow == 0) {
          return fail("Maximum number of redirects (" . self::maxRedirects . ") followed");
        } else {
          $_POST = null;
          return $this->makeRequest('GET', $redirectTo, null, $numRedirectsToFollow - 1);
        }
      } else {
        throw new HttpRedirect($redirectTo, $this->lastResponse->statusCode);
      }
    } else if ($this->lastResponse->statusCode == 404) {
      throw new HttpNotFound;
    } else {
      throw new UnexpectedHttpResponseCode("Response contained invalid/unexpected " .
        "HTTP status code: " . $this->lastResponse->statusCode);
    }
  }

  const maxRedirects = 10;
}

class HttpNonOkayResponse extends TestFailure {}
class HttpRedirect extends HttpNonOkayResponse {
  public $path;
  function __construct($path, $code) {
    $this->path = $path;
    $this->statusCode = $code;
  }
}
class HttpNotFound extends HttpNonOkayResponse {}
class UnexpectedHttpResponseCode extends HttpNonOkayResponse {}
class NoSuchForm extends TestFailure {}
class ValidationErrors extends TestFailure {}
