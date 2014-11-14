<?php

require_once 'email.php';   # sendTextEmail
require_once 'fs.php';      # getFilesInDir
require_once 'string.php';  # beginsWith

function testSendingTextEmail() {
  sendTextEmail('dan@test.net', 'bill@test.com', 'Test', 'Nothing new here.');
}

function testSendingToMultipleEmailAddresses() {
  sendTextEmail('j@test.net', 't@test.com,r@test.org', 'Test', 'Nothing new here.');
}

function testThatInvalidEmailAddressIsRejected() {
  try {
    sendTextEmail('jimmy', 'george@somewhere', 'Hey', 'Nothing');
    fail('Expected illegal email address to be rejected');
  } catch (InvalidArgumentException $_) {
    # That's what we're lookin' for.
  }
}

function testConflictingHeadersAreNotEmittedIfExplicitContentTypeIsPassed() {
  clearOutbox();
  sendTextEmail("a@test.org", "b@test.com", "Hi man", "Simple text.",
    array("Content-type: text/plain; charset=ISO-8859-1"));
  $content = expectOneEmail();
  $lines = explode("\n", $content);
  $contentTypeHeaders = array_filter($lines,
    function($l) { return beginsWith(strtolower($l), "content-type:"); });
  assertEqual(1, count($contentTypeHeaders));
}

function testGetHeaderValue() {
  $headers = array("From: some@test.org", "Reply-To: Bill@test.net", "Return-Path: bill@test.com");
  assertEqual("Bill@test.net", getHeaderValue($headers, "Reply-To"));
}

function testHasHeader() {
  $headers = array("From: jon@test.net", "Content-Type: text/plain");
  assertTrue(hasHeader($headers, 'Content-Type'));
}

function expectOneEmail() {
  $d = outboxDir();
  $fs = getFilesInDir($d);
  assertEqual(1, count($fs));
  return file_get_contents($d . "/" . $fs[0]);
}

function clearOutbox() {
  $d = outboxDir();
  foreach (getFilesInDir($d) as $f)
    unlink("$d/$f");
}

function outboxDir() {
  return getenv("PHP_SPARE_PARTS_TEST_MAILDIR");
}
