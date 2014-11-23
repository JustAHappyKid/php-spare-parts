<?php

# TODO: Rename this file to mock-email.php.

# Use functionality here to help you "mock out" PHP's email-sending functionality for
# testing purposes... The idea is to use a "mock" sendmail binary (or script) that will
# store the emails passed it somewhere (e.g., in a database or in temporary files), so
# assertions can be made against them.
#
# XXX: More to come soon (hopefully!)...

namespace SpareParts\Test\MockEmail;

require_once dirname(dirname(__FILE__)) . '/array.php';  # takeWhile
require_once dirname(dirname(__FILE__)) . '/fs.php';     # getFilesInDir

use \SpareParts\ArrayLib as A;

/**
 * Assert 'sendmail_path' was properly configured for testing environment and put its
 * directory on the PATH environment variable.
 */
function addMockSendmailToPath($pathToExecutable) {
  $filename = basename($pathToExecutable);
  $smPath = ini_get("sendmail_path");
  if ($smPath != 'mock-sendmail') {
    throw new \Exception("Expected 'sendmail_path' config variable to be set to " .
                         "'$filename' but it is set as '$smPath'");
  }
  $binDir = dirname($pathToExecutable);
  putenv("PATH=$binDir:" . getenv('PATH'));
}

function getSentEmails() {
  $d = outboxDir();
  $rawMessages = array_map(function($f) use($d) { return file_get_contents("$d/$f"); },
                           getFilesInDir($d));
  return array_map(function($m) { return parseEmail($m); }, $rawMessages);
}

function parseEmail($rawContent) {
  $lines = explode("\n", $rawContent);
  $headerLines = A\takeWhile(function($line) { return trim($line) != ''; }, $lines);
  $headers = array_map(
    function($line) {
      $h = new EmailHeader;
      list($h->label, $h->value) = array_map('trim', explode(':', $line, 2));
      return $h;
    },
    $headerLines);
  $email = new Email;
  $email->message = implode("\n", array_slice($lines, count($headerLines) + 1));
  foreach ($headers as $h) {
    switch ($h->label) {
      case 'Subject' : $email->subject = $h->value; break;
      case 'To'      : $email->to      = $h->value; break;
      case 'From'    : $email->from    = $h->value; break;
    }
  }
  $email->headers = $headers;
  return $email;
}

function clearOutbox() {
  $d = outboxDir();
  foreach (getFilesInDir($d) as $f)
    unlink("$d/$f");
}

function outboxDir() {
  $d = getenv("PHP_SPARE_PARTS_TEST_MAILDIR");
  if (empty($d))
    throw new \Exception("Environment variable PHP_SPARE_PARTS_TEST_MAILDIR is empty");
  return $d;
}

class Email {
  var $from, $to, $subject, $headers, $message;

  function getHeaderValue($key) {
    $header = A\head(array_filter($this->headers,
      function(EmailHeader $h) use($key) { return strtolower($h->label) == strtolower($key); }));
    return $header === null ? null : $header->value;
  }
}

class EmailHeader {
  var $label, $value;
}
