<?php

namespace SpareParts\Template;

require_once dirname(__FILE__) . '/token.php';
require_once dirname(dirname(__FILE__)) . '/string.php';  # withoutPrefix

use \SpareParts\Template\Token;

# TODO: This should not blindly replace all instances of the variable pattern -- it should
#       only consider those wrapped in appropriate PHP tags.
function rescopeVariables($php) {
  //return preg_replace('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', '$vars[\'\\1\']', $php);
  $tokens = token_get_all($php);
  $inString = false;
  $expandedCode = '';
  foreach ($tokens as $t) {
    if (is_array($t) && $t[0] == T_VARIABLE) {
      $varName = withoutPrefix($t[1], '$');
      if ($varName == 'this') {
        $expandedCode .= '$this';
      } else {
        $expandedVar = '$vars[\'' . $varName . '\']';
        $expandedCode .= $inString ? ('{' . $expandedVar . '}') : $expandedVar;
      }
    } else {
      if (is_string($t) && $t == '"') $inString = !$inString;
      $expandedCode .= Token\render($t);
    }
  }
  return $expandedCode;
}
