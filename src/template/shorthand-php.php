<?php

namespace SpareParts\Template;

require_once dirname(dirname(__FILE__)) . '/array.php';   # flatten
require_once dirname(dirname(__FILE__)) . '/string.php';  # beginsWith, withoutPrefix, ...

use \SpareParts\ArrayLib as A;

/**
 * Expand lines beginning with `?` or containing only a single closing curly-bracket (`}`) to
 * PHP "logic lines".  For example, the following...
 *
 *   ? if ($myBool) {
 *     <p>It's true!</p>
 *   }
 *
 * ...would yield...
 *
 *   <?php if ($myBool) { ?>
 *     <p>It's true!</p>
 *   <?php } ?>
 */
function expandShorthandPhpLogic($tpl) {
  $linesOrig = explode("\n", $tpl);
  $linesFixed = array_map(function($line) {
    if (beginsWith(trim($line), '?')) {
      return '<?php ' . withoutPrefix(trim($line), '?') . ' ?>';
    } else if (trim($line) == '}') {
      return '<?php ' . trim($line) . ' ?>';
    } else {
      return $line;
    }
  }, $linesOrig);
  return implode("\n", $linesFixed);
}

/**
 * Expand variables (alpha-numeric sequences beginning with `$`) within all "T_INLINE_HTML" parts
 * to PHP "echo tags".  For example, the following...
 *
 *   <em>Welcome to our website, $name.</em>
 *
 * ...given a value of "Joe" for variable $name, would yield...
 *
 *   <em>Welcome to our website, Joe.</em>
 */
function expandShorthandPhpVariableSubstitution($tpl) {
  $tokens = token_get_all($tpl);
  $fixed = array_map(
    function($t) {
      if (is_array($t) && $t[0] == T_INLINE_HTML)
        return array($t[0], expandVariablesInTextPart($t[1]), $t[2]);
      else return $t;
    },
    $tokens);
  return reconstructPhpFromTokens($fixed);
}

function expandVariablesInTextPart($tpl) {
  $result = '';
  $chars = str_split($tpl);
  for ($i = 0; $i < count($chars); ++$i) {
    $char = $chars[$i];
    if ($char == '$' && (ctype_alpha($chars[$i+1]) || $chars[$i+1] == '_')) {
      $vnameChars = A\takeWhile(function($c) { return ctype_alnum($c) || $c == '_'; },
                      str_split(substr($tpl, $i + 1)));
      $i += count($vnameChars);
      $result .= '<?= $' . implode('', $vnameChars) . ' ?>';
    } else {
      $result .= $char;
    }
  }
  return $result;
}

/**
 * Take tokenized PHP code (of the sort returned by 'token_get_all') and convert it back into
 * PHP code in text form.
 */
function reconstructPhpFromTokens($tokens) {
  /*
  echo "passed tokens:\n"; var_dump($tokens); echo "\n\n";
  $fixed = array_map(function($t) { return is_array($t) ? $t[1] : $t; }, $tokens);
  echo "fixed tokens:\n"; var_dump($fixed);
  */
  return implode("", array_map(function($t) { return is_array($t) ? $t[1] : $t; }, $tokens));
}
