<?php

namespace SpareParts\Template;

require_once dirname(dirname(__FILE__)) . '/array.php';   # flatten
require_once dirname(dirname(__FILE__)) . '/string.php';  # beginsWith, withoutPrefix, ...
require_once dirname(__FILE__) . '/LineByLineLexer.php';  # LineByLineLexer

use \SpareParts\ArrayLib as A;

/**
 * Expand "shorthand line" and "shorthand control-structure" syntax.
 *
 * "Shorthand line" syntax includes any line beginning with `?` (excluding whitespace).
 * For example, the following line...
 *
 *   ? $myLocalVar = 1 + 1;
 *
 * ...would become...
 *
 *    <?php $myLocalVar = 1 + 1; ?>
 *
 * "Shorthand control-structure" syntax allows for a PHP control structure (if-statement,
 * for-loop, etc) to be defined similar to above, but the "closing-bracket line" need NOT
 * begin with a `?`. For example...
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
  $parser = new LineByLineLexer($tpl);
  return expandLineByLine($parser);
}

function expandLineByLine(LineByLineLexer $parser) {
  $result = "";
  while ($parser->moreLinesLeft()) {
    $line = $parser->takeLine();
    if (beginsWith(ltrim($line), '?')) {
      $indentation = substr($line, 0, strlen($line) - strlen(ltrim($line)));
      $result .= $indentation . phpBlock(withoutPrefix(ltrim($line), '?'));
      if (endsWith(rtrim($line), '{')) {
        $innerContent = readBracketedContent($parser, $indentation);
        $result .=
          $innerContent . "\n" .
          $indentation . phpBlock('}') . "\n";
      }
    } else {
      $result .= $line . "\n";
    }
  }
  return rtrim($result);
}

function readBracketedContent(LineByLineLexer $parser, $indentation) {
  $content = "";
  $firstLineNum = $parser->lineNum() - 1;
  $line = $parser->takeLine();
  while ($line != ($indentation . '}')) {
    /*
    echo "line == '$line'\n";
    echo "seeking '" . $indentation . "}'\n\n";
    */
    $content .= $line . "\n";
    if (!$parser->moreLinesLeft())
      throw new ParseError("Reach end-of-file while searching for closing-bracket for block " .
                           "that began on line $firstLineNum", "XXX", $parser->lineNum());
    $line = $parser->takeLine();
  }
  return $content;
}

function phpBlock($c) { return "<?php $c ?>"; }

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
      $vnameChars = A\takeWhile(
        function($c) { return ctype_alnum($c) || in_array($c, array('_', '-', '>')); },
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
