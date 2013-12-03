<?php

namespace SpareParts\Webapp\Filters;

use \SpareParts\Webapp\Filter, \SpareParts\Webapp\HttpResponse,
  \SpareParts\Webapp\MaliciousRequestException;

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

  public function incoming() {
    if (count($_POST)) {
      if (!isset($_POST[$this->nameForNameInput]) || !isset($_POST[$this->nameForTokenInput]))
        throw new MaliciousRequestException("No CSRF-prevention token found in POST data");
      $name  = $_POST[$this->nameForNameInput];
      $token = $_POST[$this->nameForTokenInput];
      if (!$this->isValidToken($name, $token))
        throw new MaliciousRequestException("Invalid CSRF-prevention token provided");
    }
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
        // XXX: Maybe make this configurable via the constructor, so we can avoid the
        // XXX: need to insert such text into HTML that end-users (e.g., hackers) could see?
//        if (strpos($m[1],"nocsrf")!==false) { continue; }

        $name = "SpareParts.CSRFGuard." . mt_rand(0, mt_getrandmax());
//        $token=csrfguard_generate_token($name);
        $token = $this->generateToken();
        $_SESSION[$name] = $token;
        $newForm = "<form{$m[1]}>\n" .
          "<input type='hidden' name='{$this->nameForNameInput}' value='{$name}' /> " .
          "<input type='hidden' name='{$this->nameForTokenInput}' value='{$token}' />{$m[2]}</form>";
        $html = str_replace($m[0], $newForm, $html);
      }
    }
    return $html;
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

  private function isValidToken($varName, $expectedToken) {
    $token = @ $_SESSION[$varName];
    unset($_SESSION[$varName]);
//    if ($token === false)
//      return true;
    return $token === $expectedToken;
  }
}
