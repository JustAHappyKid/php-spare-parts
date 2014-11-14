<?php

require_once dirname(__FILE__) . '/array.php';      # head
require_once dirname(__FILE__) . '/string.php';     # beginsWith
require_once dirname(__FILE__) . '/utf8.php';       # hasInvalidUTF8Chars
require_once dirname(__FILE__) . '/validation.php'; # isValidEmailAddr

use \SpareParts\Validation as V, \SpareParts\ArrayLib as A;

function sendEmail($from, $to, $subject, $message, Array $headers = array()) {
  sendTextEmail($from, $to, $subject, $message, $headers);
}

function sendTextEmail($from, $to, $subject, $message, Array $headers = array()) {

  if (!V\isValidEmailAddr($from, $allowExtendedFormat = true)) {
    throw new InvalidArgumentException("Invalid email address for \$from parameter: $from");
  }

  if (trim($to) == "") throw new InvalidArgumentException(
                         "No recipient(s) specified in \$to parameter");

  $allRecips = array_map('trim', explode(',', $to));
  foreach ($allRecips as $r) {
    if (!V\isValidEmailAddr($r, $allowExtendedFormat = true)) {
      throw new InvalidArgumentException("Invalid email address in \$to parameter: $r");
    }
  }

  $defaultHeaders = array("From: $from", "Reply-To: $from", "Return-Path: $from",
                          "Content-Type: text/plain; charset=UTF-8");
  $allHeaders = $headers;
  foreach ($defaultHeaders as $h) {
    if (!hasHeader($headers, headerKey($h)))
      $allHeaders []= $h;
  }
  
  foreach ($allHeaders as $h) {
    if (trim($h) == '') throw new InvalidArgumentException("Empty string in \$headers array");
    $parts = explode(':', $h, 2);
    $hdrName = $parts[0];
    if (!preg_match('/[-A-Za-z]+/', $hdrName)) {
      throw new InvalidArgumentException("Invalid header provided: " . $h);
    }
  }

  # Ensure the message is properly encoded in UTF-8 (if the Content-Type header does indeed
  # specify UTF-8 as the character-encoding)...
  $contentType = getHeaderValue($allHeaders, "Content-Type");
  $parts = explode(';', $contentType);
  if (count($parts) == 2) {
    $charset = strtolower(withoutPrefix($parts[1], "charset="));
    if ($charset == 'utf-8' && hasInvalidUTF8Chars($message))
      throw new InvalidArgumentException("Message body has non-UTF-8 characters but " .
        "Content-Type specifies UTF-8 as charset");
  }

  mail($to, $subject, $message, implode("\r\n", $allHeaders), "-f \"$from\"");
}

/* private */ function getHeaderValue(Array $headers, $key) {
  $matches = array_filter($headers,
    function($h) use($key) { return beginsWith(strtolower($h), strtolower($key) . ':'); });
  return count($matches) > 0 ? trim(substr(A\head($matches), strlen($key) + 1)) : null;
}

/* private */ function hasHeader(Array $headers, $key) {
  return getHeaderValue($headers, $key) != null;
}

/* private */ function headerKey($header) {
  return A\head(explode(':', $header));
}
