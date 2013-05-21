<?php

require_once 'email.php';

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
