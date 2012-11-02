<?php

namespace MyPHPLibs\ErrorHandling;

use \Exception;

function initErrorHandling() {
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

class StandardPhpError extends ErrorHandlerInvokedException {}
class StandardPhpWarning extends ErrorHandlerInvokedException {}
class StandardPhpNotice extends ErrorHandlerInvokedException {}
class UserLevelPhpError extends ErrorHandlerInvokedException {}
class UserLevelPhpWarning extends ErrorHandlerInvokedException {}
class UserLevelPhpNotice extends ErrorHandlerInvokedException {}

function errorHandler($errno, $errstr, $errfile, $errline) {

  $errorTypes = array(
    E_ERROR           => 'StandardPhpError',
    E_WARNING         => 'StandardPhpWarning',
    E_NOTICE          => 'StandardPhpNotice',
    //E_PARSE           => "Parsing Error",
    //E_CORE_ERROR      => "Core Error",
    //E_CORE_WARNING    => "Core Warning",
    //E_COMPILE_ERROR   => "Compile Error",
    //E_COMPILE_WARNING => "Compile Warning",
    E_USER_ERROR      => 'UserLevelPhpError',
    E_USER_WARNING    => 'UserLevelPhpWarning',
    E_USER_NOTICE     => 'UserLevelPhpNotice'
  );

  // XXX: I had to turn this off, as it was causing my web interface to
  //      render improperly.  I'm not sure why, however; and I'm also not even
  //      sure why we want the output buffer(s) to be cleared...
  // Clear any output buffers that have been set
  //while (ob_get_level()) {
  //  ob_end_clean();
  //}

  // If PHP is configured to ignore errors of the type that we've been passed,
  // we'll ignore the error.
  if (!($errno & error_reporting())) {
    return;
  }

  $exceptionClass = isset($errorTypes[$errno]) ?
    ('\\MyPHPLibs\\ErrorHandling\\' . $errorTypes[$errno]) : null;
  $errMsg = htmlspecialchars_decode($errstr);
  if ($exceptionClass) {
    throw new $exceptionClass($errMsg);
  } else {
    throw new Exception($errMsg);
  }
}

function exceptionHandler($exception) {

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

  // We'll wrap this whole function in a try block.  If an exception gets
  // thrown from our *exception handler*, PHP will prevent the infinite loop,
  // but it will give a completely cryptic and unrelated error message.
  try {

    // -------------------------------------------------------------------------
    // - Begin Error Detail Message --------------------------------------------
    // -------------------------------------------------------------------------

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
          $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']) .
          "\n\n";
    }

    // -------------------------------------------------------------------------
    // - End Error Detail Message --------------------------------------------
    // -------------------------------------------------------------------------

    presentErrorReport($exception->getMessage(), $body);
    exit();
  } catch (Exception $e) {
    exit("UH-OH!  An exception was raised from within the exception " .
      "handler!  Exception's details follow:\n" .
      $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
  }
}


/**
 * This function will simply display the given error to the user, and depending
 * on whether or not the server is configured as a "production" instance, will
 * display the error using either a "user-friendly" error page or a
 * "developer-friendly" error page.  In either case, if the ERROR_EMAIL constant
 * is set, an email containing the error report will be sent to the address
 * specified by that constant.
 */
function presentErrorReport($briefDetail, $fullReport) {
  if (php_sapi_name() == 'cli') {
    # TODO: Shouldn't we still send an email if DEVELOPMENT_MODE is off and ADMIN_EMAIL is set,
    #       even if we're running under the CLI??
    echo "\n$fullReport\n\n";
  } else {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline');
    if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
      echo "<pre>\n" . htmlspecialchars($fullReport) . "\n</pre>";
    } else if (defined('ADMIN_EMAIL')) {
      echo '<div style="color: #700; background-color: #fcc; padding: 0 0.9em;
                        border: 0.1em solid #daa; border-radius: 0.2em;
                        max-width: 40em; margin: 3em auto;">' .
        "<p><strong>Sorry, something went wrong.</strong></p> " .
        "<p>Our team has been notified of the problem, but it would be helpful if you
           <a href=\"mailto:" . ADMIN_EMAIL . "\">email us</a> and tell us what you
           were doing just before and leading up to this failure.  We'll do our best
           to get this fixed ASAP!</p></div>\n";
      mail(ADMIN_EMAIL, "PHP Error Report", $fullReport);
    } else {
      echo "<p>Uh-oh -- something went wrong, and DEVELOPMENT_MODE is off and ADMIN_EMAIL is " .
        "not defined!</p>\n";
    }
  }
}
