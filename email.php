<?php

function send_email($from, $to, $subject, $message) {
  mail($to, $subject, $message, "From: $from");
}