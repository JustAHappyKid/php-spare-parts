<?php

require_once 'email.php';               # sendTextEmail
require_once 'string.php';              # beginsWith
require_once 'test/mock-sendmail.php';  # clearOutbox

use \SpareParts\Test\MockEmail;

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
  MockEmail\clearOutbox();
  sendTextEmail("a@test.org", "b@test.com", "Hi man", "Simple text.",
    array("Content-type: text/plain; charset=ISO-8859-1"));
  $emails = MockEmail\getSentEmails();
  assertEqual(1, count($emails));
  $e = reset($emails);
  $contentType = $e->getHeaderValue("Content-Type");
  assertTrue($contentType != null);
  assertTrue(beginsWith($contentType, "text/plain"));
}

function testGetHeaderValue() {
  $headers = array("From: some@test.org", "Reply-To: Bill@test.net", "Return-Path: bill@test.com");
  assertEqual("Bill@test.net", getHeaderValue($headers, "Reply-To"));
}

function testHasHeader() {
  $headers = array("From: jon@test.net", "Content-Type: text/plain");
  assertTrue(hasHeader($headers, 'Content-Type'));
}
