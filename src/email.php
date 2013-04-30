<?php

require_once dirname(__FILE__) . '/validation.php'; # isValidEmailAddr

use \InvalidArgumentException;

function sendEmail($from, $to, $subject, $message, Array $headers = array()) {
  sendTextEmail($from, $to, $subject, $message, $headers);
}

function sendTextEmail($from, $to, $subject, $message, Array $headers = array()) {
  if (!isValidEmailAddr($from, $allowExtendedFormat = true)) {
    throw new InvalidArgumentException("Invalid email address for \$from parameter: $from");
  } else if (!isValidEmailAddr($to, $allowExtendedFormat = true)) {
    throw new InvalidArgumentException("Invalid email address for \$to parameter: $to");
  }
  $defaultHeaders = array("From: $from", "Reply-To: $from", "Return-Path: $from",
                          "Content-Type: text/plain");
  $allHeaders = array_merge($defaultHeaders, $headers);
  mail($to, $subject, $message, implode("\r\n", $allHeaders), "-f \"$from\"");
}
