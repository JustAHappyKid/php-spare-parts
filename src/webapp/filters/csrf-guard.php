<?php

namespace SpareParts\Webapp\Filters;

require_once dirname(dirname(dirname(__FILE__))) . '/web-client/html-form.php'; # HtmlForm
require_once dirname(dirname(__FILE__)) . '/ui.php';                            # simpleErrorPage

use \SpareParts\Webapp\Filter, \SpareParts\Webapp\HttpResponse,
  \SpareParts\Webapp\MaliciousRequestException, \SpareParts\WebClient\HtmlForm,
  \SpareParts\Webapp\UI;

/**
 * Attempt to prevent Cross-Site Request Forgery (CSRF) attacks by adding some "magic dust"
 * to all outgoing HTML forms and asserting said "magic dust" is present in all incoming POST
 * requests. Learn more here: https://www.owasp.org/index.php/Cross-Site_Request_Forgery_(CSRF)
 *
 * @package SpareParts\Webapp\Filters
 */
class CSRFGuard implements Filter {

  protected $nameForNameInput  = '__sp_guard_name';
  protected $nameForTokenInput = '__sp_guard_token';

  /**
   * Should the given form be CSRF-guarded? The default implementation will include
   * all forms except those that use a "get" method.
   */
  protected function shouldGuardForm(HtmlForm $f) {
    return strtolower($f->method) != 'get';
  }

  public function incoming() {

    if (count($_POST)) {
      if (!isset($_POST[$this->nameForNameInput]) || !isset($_POST[$this->nameForTokenInput]))
        throw new MaliciousRequestException("No CSRF-prevention token found in POST data");
      $name  = $_POST[$this->nameForNameInput];
      $token = $_POST[$this->nameForTokenInput];
      if (!$this->isValidToken($name, $token))
        return new HttpResponse($statusCode = 400, $contentType = 'text/html',
          $content = UI\simpleErrorPage("
            <h1>Invalid CSRF-Prevention Token</h1>
            <p>Sorry, but we could not validate the authenticity of your request. This can happen
              if your session has expired or you cleared your cookies. Please try completing
              the action you intended to again, and if you continue to see this validation error,
              please let us know.</p>
            <p>Though it may prove a minor annoyance, we put this mechanism in place to keep our
              users safe from
              <a href=\"https://en.wikipedia.org/wiki/Cross-site_request_forgery\"
                 >Cross-Site Request Forgery</a> attacks. Thanks for your understanding!</p>
          "));
    }

    /* If all looks okay, we return null to indicate so. */
    return null;
  }

  # TODO: Only pass through filter if Content-Type indicates it is indeed HTML??
  public function outgoing(HttpResponse $response) {
    $response->content = $this->replaceForms($response->content);
  }

  private function replaceForms($html) {
    $matches = null;
    preg_match_all("/<form(.*?)>(.*?)<\\/form>/is", $html, $matches, PREG_SET_ORDER);
    if (is_array($matches)) {
      foreach ($matches as $m) {
        if ($this->shouldGuardForm(HtmlForm::fromString($m[0]))) {
          $name = "SpareParts.CSRFGuard." . mt_rand(0, mt_getrandmax());
          $token = $this->generateToken();
          $_SESSION[$name] = $token;
          $newForm = $this->reconstructForm($m[1], $m[2], $name, $token);
          $html = str_replace($m[0], $newForm, $html);
        }
      }
    }
    return $html;
  }

  private function reconstructForm($attrs, $content, $name, $token) {
    return "<form " . trim($attrs) . ">\n" .
      "<input type='hidden' name='{$this->nameForNameInput}' value='{$name}' />\n" .
      "<input type='hidden' name='{$this->nameForTokenInput}' value='{$token}' />\n" .
      "$content</form>";
  }

  private function generateToken() {
    if (function_exists("hash_algos") and in_array("sha512", hash_algos())) {
      $token = hash("sha512", mt_rand(0, mt_getrandmax()));
    } else {
      $token = ' ';
      for ($i=0;$i<128;++$i) {
        $r = mt_rand(0,35);
        if ($r < 26) $c = chr(ord('a') + $r);
        else $c = chr(ord('0') + $r-26);
        $token .= $c;
      }
    }
    return $token;
  }

  private function isValidToken($varName, $submittedToken) {
    if (!is_string($varName) || !is_string($submittedToken))
      throw new MaliciousRequestException("Invalid data-type provided for CSRF-guard name/token");
    $expectedToken = @ $_SESSION[$varName];
    unset($_SESSION[$varName]);
    return $expectedToken === $submittedToken;
  }
}
