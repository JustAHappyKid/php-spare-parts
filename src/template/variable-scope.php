<?php

namespace SpareParts\Template;

require_once dirname(__FILE__) . '/token.php';
require_once dirname(dirname(__FILE__)) . '/string.php';  # withoutPrefix

use \SpareParts\Template\Token;

function rescopeVariables($php, Array $vars) {
  $varNames = array_keys($vars);
  $tokens = token_get_all($php);
  $inString = false;
  $expandedCode = '';
  foreach ($tokens as $t) {
    if (is_array($t) && $t[0] == T_VARIABLE) {
      $varName = withoutPrefix($t[1], '$');
      if ($varName == 'this') {
        $expandedCode .= '$' . $varName;
      } else if (!in_array($varName, $varNames)) {
//        $expandedCode .= 'if (!isset($' . $varName .'))' .
//          'throw new \\SpareParts\\Template\\UndefinedVariable("' . $varName . '");';
        $expandedCode .= '$' . $varName;
      } else {
        $expandedVar = '$this->__vars[\'' . $varName . '\']';
        $expandedCode .= $inString ? ('{' . $expandedVar . '}') : $expandedVar;
      }
    } else {
      if (is_string($t) && $t == '"') $inString = !$inString;
      $expandedCode .= Token\render($t);
    }
  }
  return $expandedCode;
}
