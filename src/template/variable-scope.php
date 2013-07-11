<?php

# TODO: This should not blindly replace all instances of the variable pattern -- it should
#       only consider those wrapped in appropriate PHP tags.
function rescopeVariables($php) {
  return preg_replace('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', '$vars[\'\\1\']', $php);
  /*
  foreach ($allParts as $p) {
    if ($p instanceof PHPPart)
      $p->content = preg_replace('/\$([a-zA-Z0-9_]+)/', '$vars[\'\\1\']', $p->content);
  }
  */
}
