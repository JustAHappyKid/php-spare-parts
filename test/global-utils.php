<?php

require_once 'global-utils.php';

function testPathJoin() {
  assertEqual("big/long/path.txt", pathJoin("big/long", "path.txt"));
  assertEqual("big/long/path.txt", pathJoin("big/long/", "path.txt"));
  assertEqual("/big/long/path.txt", pathJoin("/big", "long", "path.txt"));
}

