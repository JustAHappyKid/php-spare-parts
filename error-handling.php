<?php

function init_error_handling() {
  ini_set('docref_root', null);
  ini_set('docref_ext', null);
  set_error_handler('error_handler');
  set_exception_handler('exception_handler');
}

function error_handler($errno, $errstr, $errfile, $errline) {

  $error_type = array(
    E_ERROR           => "PHP Error",
    E_WARNING         => "PHP Warning",
    E_PARSE           => "Parsing Error",
    E_NOTICE          => "PHP Notice",
    E_CORE_ERROR      => "Core Error",
    E_CORE_WARNING    => "Core Warning",
    E_COMPILE_ERROR   => "Compile Error",
    E_COMPILE_WARNING => "Compile Warning",
    E_USER_ERROR      => "PHP User Error",
    E_USER_WARNING    => "PHP User Warning",
    E_USER_NOTICE     => "PHP User Notice"
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

  throw new Exception($error_type[$errno] . ": " . htmlspecialchars_decode($errstr));
}

function exception_handler($exception) {

  function hash_to_str($h) {
    $str = "";
    foreach ($h as $k => $v) {
      $str .= "  $k: " . print_r($v, true) . "\n";
    }
    return substr($str, 0, -1);
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
        "\$_SERVER: \n" . hash_to_str($_SERVER) . "\n\n" .
        "\$_POST: \n" . hash_to_str($_POST) . "\n\n" .
        "\$_GET: \n" . hash_to_str($_GET) . "\n\n" .
        "\$_COOKIE: \n" . hash_to_str($_COOKIE) . "\n\n" .
        (isset($_SESSION) ? 
          ("\$_SESSION: \n" . hash_to_str($_SESSION)) :
          "\$_SESSION is not set.") . "\n\n" .
        "IP Address: " . (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ?
          $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']) .
          "\n\n";
    }

    // -------------------------------------------------------------------------
    // - End Error Detail Message --------------------------------------------
    // -------------------------------------------------------------------------

    process_error_report($exception->getMessage(), $body);
    exit();
  }
  catch (Exception $e) {
    exit("UH-OH!  An exception was raised from within the exception " .
      "handler!  The exception's message follows:\n" . $e->getMessage());
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
function process_error_report($brief_detail, $full_report) {
  if (DEVELOPMENT_MODE) {
    echo "<pre>\n" . htmlspecialchars($full_report) . "\n</pre>";
  } else {
    echo "
      <p>Sorry, something went wrong.  Our team has been notified of the
        problem, but it would be helpful if you
        <a href=\"mailto:" . ADMIN_EMAIL . "\">email us</a> and tell us what
        you were doing that led to this failure.  We'll do our best to get
        this fixed ASAP!</p>";
    mail(ADMIN_EMAIL, "PHP Error Report", $full_report);
  }
}
