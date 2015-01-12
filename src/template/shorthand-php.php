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

function expandLineByLine(LineByLineLexer $scanner) {
  $result = "";
  while ($scanner->moreLinesLeft()) {
    $result .= processLine($scanner->takeLine(), $scanner);
  }
  return rtrim($result);
}

function processLine($line, LineByLineLexer $scanner) {
  if (beginsWith(ltrim($line), '?')) {
    $indentation = substr($line, 0, strlen($line) - strlen(ltrim($line)));
    $block = $indentation . phpBlock(withoutPrefix(ltrim($line), '?'));
    if (endsWith(rtrim($line), '{')) {
      $innerContent = readBracketedContent($scanner, $indentation);
      $block .=
        $innerContent . "\n" .
        $indentation . phpBlock('}') . "\n";
    }
    return $block;
  } else {
    return "$line\n";
  }
}

function readBracketedContent(LineByLineLexer $scanner, $indentation) {
  $firstLineNum = $scanner->lineNum() - 1;
  $line = $scanner->takeLine();
  $content = "";
  while (!beginsWith($line, ($indentation . '}'))) {
//    echo "line == '$line'\n";
//    echo "seeking '" . $indentation . "}'\n\n";
    $content .= processLine($line, $scanner);
    if ($scanner->noLinesLeft())
      throw new ParseError("Reached end-of-file while searching for closing-bracket for block " .
        "that began on line $firstLineNum", "XXX", $scanner->lineNum());
    $line = $scanner->takeLine();
  }
  if (endsWith(rtrim($line), '{')) {
    $content .= $indentation . phpBlock(ltrim($line)) . "\n" .
      readBracketedContent($scanner, $indentation);
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

  if (strstr($tpl, '<?')) {
    throw new ParseError("Use of \"short open tag\" (<?) is not permitted to avoid " .
                         "compatibility problems across different PHP configurations",
                         "XXX", "XXX");
  }

  $result = '';
  $chars = str_split($tpl);
  for ($i = 0; $i < count($chars); ++$i) {
    $char = $chars[$i];
    if ($char == '$' && (ctype_alpha($chars[$i+1]) || $chars[$i+1] == '_')) {
      /*
      $vnameChars = A\takeWhile(
        function($c) { return ctype_alnum($c) || in_array($c, array('_', '-', '>')); },
        str_split(substr($tpl, $i + 1)));
      $i += count($vnameChars);
      */
      $vnameChars = '$';
      $i += 1;
      while (ctype_alnum(A\at($chars, $i)) || A\at($chars, $i) == '_' ||
             (A\at($chars, $i) == '-' && A\at($chars, $i+1) == '>')) {
        $vnameChars .= $chars[$i];
        $i += 1;
        if (A\at($chars, $i) == '>') {
          $vnameChars .= '>';
          $i += 1;
        }
      }
      $i -= 1;
      $result .= '<?= ' . $vnameChars . ' ?>';
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
