<?php

function sendEmail($from, $to, $subject, $message, Array $headers = array()) {
  $allHeaders = array_merge(array("From: $from"), $headers);
  mail($to, $subject, $message, implode("\r\n", $allHeaders));
}
