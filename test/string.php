<?php

require_once 'string.php';

function testWithoutSuffix() {
  assertEqual('index', withoutSuffix('index.php', '.php'));
  assertEqual('index.php', withoutSuffix('index.php', '.txt'));
  assertEqual('/path/to/file', withoutSuffix('/path/to/file.txt', '.txt'));
  assertEqual('/path/to/file.', withoutSuffix('/path/to/file.txt', 'txt'));
}
