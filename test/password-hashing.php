<?php

require_once 'password-hashing.php';

function testPasswordHashingWithBcrypt() {
  $hash = password_hash('strawberryp@tch', PASSWORD_BCRYPT);
  assertTrue(password_verify('strawberryp@tch', $hash));
}
