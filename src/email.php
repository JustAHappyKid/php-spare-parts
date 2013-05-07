<?php

require_once dirname(__FILE__) . '/validation.php'; # isValidEmailAddr

use \InvalidArgumentException, \MyPHPLibs\Validation as V;

function sendEmail($from, $to, $subject, $message, Array $headers = array()) {
  sendTextEmail($from, $to, $subject, $message, $headers);
}

function sendTextEmail($from, $to, $subject, $message, Array $headers = array()) {
  if (!V\isValidEmailAddr($from, $allowExtendedFormat = true)) {
    throw new InvalidArgumentException("Invalid email address for \$from parameter: $from");
  }
  $allRecips = array_map('trim', explode(',', $to));
  foreach ($allRecips as $r) {
    if (!V\isValidEmailAddr($r, $allowExtendedFormat = true)) {
      throw new InvalidArgumentException("Invalid email address in \$to parameter: $r");
    }
  }
  $defaultHeaders = array("From: $from", "Reply-To: $from", "Return-Path: $from",
                          "Content-Type: text/plain");
  $allHeaders = array_merge($defaultHeaders, $headers);
  mail($to, $subject, $message, implode("\r\n", $allHeaders), "-f \"$from\"");
}
