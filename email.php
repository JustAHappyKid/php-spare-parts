<?php

function sendEmail($from, $to, $subject, $message) {
  mail($to, $subject, $message, "From: $from");
}