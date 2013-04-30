<?php

namespace MyPHPLibs\ErrorHandling;

require_once dirname(__FILE__) . '/types.php';
require_once dirname(__FILE__) . '/email.php';                  # sendTextEmail
require_once dirname(__FILE__) . '/webapp/current-request.php'; # getHost

use \Exception, \MyPHPLibs\Webapp\CurrentRequest;

function initErrorHandling($sendReportsTo) {
  global $__MyPHPLibs_ErrorHandling_sendReportsTo;
  $__MyPHPLibs_ErrorHandling_sendReportsTo = $sendReportsTo;
  ini_set('docref_root', null);
  ini_set('docref_ext', null);
  set_error_handler('\\MyPHPLibs\\ErrorHandling\\errorHandler');
  set_exception_handler('\\MyPHPLibs\\ErrorHandling\\exceptionHandler');
}

class ErrorHandlerInvokedException extends Exception {
  public function getAdjustedTraceAsString() {
    $orig = parent::getTrace();
    //$new = array_slice($orig, 1);
    $new = $orig;
    $str = '';
    foreach ($new as $pos => $layer) {
      if (empty($layer['file'])) {
        $str .= "???\n";
      } else {
        $str .= "#$pos {$layer['file']} on line {$layer['line']}: {$layer['function']}\n";
      }
    }
    return $str;
  }
}

class Error      extends ErrorHandlerInvokedException {}
class Warning    extends ErrorHandlerInvokedException {}
class Notice     extends ErrorHandlerInvokedException {}
class Deprecated extends ErrorHandlerInvokedException {}

class StandardPhpError    extends Error {}
class StandardPhpWarning  extends Warning {}
class StandardPhpNotice   extends Notice {}
class UserLevelPhpError   extends Error {}
class UserLevelPhpWarning extends Warning {}
class UserLevelPhpNotice  extends Notice {}
class StrictStandard      extends ErrorHandlerInvokedException {}
class RecoverableError    extends Error {}
class PhpDeprecated       extends Deprecated {}
class UserDeprecated      extends Deprecated {}

function errorHandler($errno, $errstr, $errfile, $errline) {

  $errorTypes = array(
    E_ERROR             => 'StandardPhpError',
    E_WARNING           => 'StandardPhpWarning',
    E_NOTICE            => 'StandardPhpNotice',
    E_USER_ERROR        => 'UserLevelPhpError',
    E_USER_WARNING      => 'UserLevelPhpWarning',
    E_USER_NOTICE       => 'UserLevelPhpNotice',
    E_STRICT            => 'StrictStandard',
    E_RECOVERABLE_ERROR => 'RecoverableError',
    E_DEPRECATED        => 'PhpDeprecated',
    E_USER_DEPRECATED   => 'UserDeprecated');

  // XXX: This was causing problems at one point or another...
  //      Do we need/want it?
  // Clear any output buffers that have been set
  //while (ob_get_level()) {
  //  ob_end_clean();
  //}

  # If PHP is configured to ignore errors of the type that we've been passed,
  # we'll ignore the error.
  if (!($errno & error_reporting())) {
    return;
  } else {
    $exceptionClass = isset($errorTypes[$errno]) ?
      ('\\MyPHPLibs\\ErrorHandling\\' . $errorTypes[$errno]) : null;
    $errMsg = htmlspecialchars_decode($errstr);
    if ($exceptionClass) {
      throw new $exceptionClass($errMsg);
    } else {
      throw new Exception($errMsg);
    }
  }
}

function exceptionHandler($exception) {
  # We'll wrap this whole function in a try block.  If an exception gets
  # thrown from our *exception handler*, PHP will prevent the infinite loop,
  # but it will give a completely cryptic and unrelated error message (or at
  # least that was the case at one point in time, with older versions of PHP).
  try {
    $report = constructErrorReport($exception);
    presentErrorReport($report);
    exit();
  } catch (Exception $e) {
    exit("UH-OH!  An exception was raised from within the exception " .
      "handler!  Exception's details follow:\n" .
      $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
  }
}

function constructErrorReport($exception) {

  function renderGlobalVar($name, $value) {
    $str = '$' . $name . ': ';
    if ($value === null) {
      $str .= 'null';
    } else if (is_array($value) && count($value) == 0) {
      $str .= 'empty';
    } else if (is_array($value)) {
      $lines = array();
      foreach ($value as $k => $v) $lines []= "  $k: " . print_r($v, true);
      $str .= "\n" . implode("\n", $lines);
    } else {
      $str .= 'unexpected value of type ' . gettype($value) . '!';
    }
    return $str;
  }

  $body =
    "A thrown " . get_class($exception) . " went uncaught;" .
    " details follow...\n\n" .
    "Message: " . $exception->getMessage() . "\n\n" .
    "The exception occurred in file " . $exception->getFile() .
    " on line " . $exception->getLine() . ".\n\n" .
    "Stack trace:\n" . $exception->getTraceAsString() . "\n\n" .
    "Time: " . date('r') . "\n\n";

  if (empty($_SERVER['REQUEST_METHOD'])) {
    $body .= "This PHP instance did not seem to be invoked via an HTTP request, " .
      "as \$_SERVER['REQUEST_METHOD'] is empty.";
  } else {
    $body .=
      "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n\n" .
      "URL: http" .
        ((isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') ? 's' : '') .
        '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "\n" .
      "Referring URL: " .
        (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] :
        "(no referrer or referrer not reported)") . "\n\n" .
      renderGlobalVar('_SERVER',  $_SERVER)  . "\n\n" .
      renderGlobalVar('_POST',    $_POST)    . "\n\n" .
      renderGlobalVar('_GET',     $_GET)     . "\n\n" .
      renderGlobalVar('_COOKIE',  $_COOKIE)  . "\n\n" .
      (isset($_SESSION) ? 
        //("\$_SESSION: \n" . renderHash($_SESSION)) :
        renderGlobalVar('_SESSION', $_SESSION) :
        "\$_SESSION is not set.") . "\n\n" .
      "IP Address: " . (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ?
        $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']);
  }

  return $body;
}

/**
 * If PHP is running in command-line (CLI) mode, this function will simply output
 * the provided error report; otherwise (when presumably being accessed as a web
 * script), this function will display an appropriate error to the user, and
 * depending on how PHP is configured (the value of 'display_errors'), will
 * display the error using either a "user-friendly" error page or a
 * "developer-friendly" error page.
 *
 * If an email address has been configured for error reports, an appropriate
 * email will be sent there.
 */
function presentErrorReport($fullReport, $email = null) {
  global $__MyPHPLibs_ErrorHandling_sendReportsTo;
  $sendReportTo = $email ? $email : $__MyPHPLibs_ErrorHandling_sendReportsTo;
  if (php_sapi_name() == 'cli') {
    # TODO: Shouldn't we still send an email if $__MyPHPLibs_ErrorHandling_sendReportsTo
    #       is set, even if we're running under the CLI??
    echo "\n$fullReport\n\n";
  } else {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline');
    $displayErrors = readBoolFromStr(ini_get('display_errors'));
    if ($displayErrors) {
      echo "<pre>\n" . htmlspecialchars($fullReport) . "\n</pre>";
    } else if ($sendReportTo) {
      echo '<div style="color: #700; background-color: #fcc; padding: 0 0.9em;
                        border: 0.1em solid #daa; border-radius: 0.2em;
                        max-width: 40em; margin: 3em auto;">' .
        "<p><strong>Sorry, something went wrong.</strong></p> " .
        "<p>Our team has been notified of the problem, but it would be helpful if you
           <a href=\"mailto:$sendReportTo\">email us</a> and tell us what you
           were doing just before and leading up to this failure.  We'll do our best
           to get this fixed ASAP!</p></div>\n";
      //mail($sendReportTo, "PHP Error Report", $fullReport);
      $host = CurrentRequest\getHost();
      sendTextEmail("no-reply@$host", $sendReportTo, "PHP Error Report [$host]", $fullReport);
    } else {
      echo "<p>Uh-oh &ndash; something went wrong, but 'display_errors' is off and no email " .
        "address was configured (via initErrorHandling) for receiving error reports!</p>\n";
    }
  }
}
