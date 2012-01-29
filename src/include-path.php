<?php

function addToIncludePath($newPath) {
  $paths = explode(':', get_include_path());
  if (!in_array($newPath, $paths)) {
    $paths []= $newPath;
    set_include_path(implode(':', $paths));
  }
}
