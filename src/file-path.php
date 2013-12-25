<?php

namespace SpareParts\FilePath;

use \InvalidArgumentException;

function join() {
  if (func_num_args() == 0) {
    throw new InvalidArgumentException("pathJoin() requires at least one parameter");
  }
  $path = "";
  for ($i = 0; $i < func_num_args(); ++$i) {
    $param = func_get_arg($i);
    if ($param == "") continue;
    if ($i != 0 and $param[0] == '/') {
      throw new InvalidArgumentException("Only the first component passed to pathJoin() " .
        "may be an absolute path (that is, only the first component may begin with a slash)");
    }
    $path .= $param;
    if (substr($param, -1, 1) != '/') $path .= '/';
  }
  return substr($path, 0, -1);  # Return the path minus the last slash.
}

/**
 * Tidy up a directory name.
 * Examples:
 *   extra///slashes//   => extra/slashes
 *   /dot/./slash/.     => /dot/slash
 *   ./some/path        => some/path
 *   /path/to/dir       => /path/to/dir
 *  let/us/step/../back => let/us/back
 * @param string $path Path to normalize
 * @return string Normalized path
 */
function normalize($path) {
  $isAbsolutePath = $path[0] == '/';
//  $relativePath = $isAbsolutePath ? substr($path, 1) : $path;
  $combined = array_reduce(explode('/', $path),
    function($a, $b) {
      if ($b === "" || $b === ".")  return $a;
      else if ($b === "..")         return dirname($a) == '.' ? '' : dirname($a);
      else if ($a === "")           return $b;
      else                          return "$a/$b";
    }, '');
  return ($isAbsolutePath ? '/' : '') . $combined;
}
