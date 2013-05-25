<?php

namespace SpareParts\Template;

require_once dirname(dirname(__FILE__)) . '/array.php';
require_once dirname(dirname(__FILE__)) . '/string.php';

use \SpareParts\ArrayLib as A;

/**
 * Represents piece of template...
 */
abstract class Part {
  public $content;
  function __construct($c) {
    $this->content = $c;
  }
  abstract function render();
}

abstract class PHPPart extends Part {}
class PHPLogicPart  extends PHPPart {
  function render() { return '<? ' . $this->content . '?>'; }
}
class PHPOutputPart extends PHPPart {
  function render() { return '<?= ' . $this->content . '?>'; }
}
class TextPart      extends Part {
  function render() { return $this->content; }
}

function renderFromString($tpl, $vars) {
  $linesOrig = explode("\n", $tpl);
  $allParts = A\flatten(array_map(function($line) {
    if (beginsWith(trim($line), '?')) {
      return array(new PHPLogicPart(withoutPrefix(trim($line), '?')));
    } else {
      $parts = array();
      $txt = '';
      $chars = str_split($line);
      for ($i = 0; $i < strlen($line); ++$i) {
        $char = $chars[$i];
        if ($char == '$') {
          if ($txt != '') {
            $parts []= new TextPart($txt);
            $txt = '';
          }
          $vnameChars = A\takeWhile(function($c) { return ctype_alnum($c) || $c == '_'; },
                          str_split(substr($line, $i + 1)));
          $i += count($vnameChars);
          $parts []= new PHPOutputPart('$' . implode('', $vnameChars));
        } else {
          $txt .= $char;
        }
      }
      if ($txt != '') $parts []= new TextPart($txt);
      return $parts;
    }
  }, $linesOrig));
  foreach ($allParts as $p) {
    if ($p instanceof PHPPart)
      $p->content = preg_replace('/\$([a-zA-Z0-9_]+)/', '$vars[\'\\1\']', $p->content);
  }
  $final = implode('', array_map(function(Part $p) { return $p->render(); }, $allParts));
  ob_start();
  eval("?>$final");
  $rendered = ob_get_contents();
  ob_end_clean();
  return $rendered;
}

/*
function renderFromString($tpl, $vars) {
  $rescoped = preg_replace('/\$([a-zA-Z0-9_]+)/', '{$vars[\'\\1\']}', $tpl);
  // echo "rescoped: $rescoped\n";
  eval("\$result = \"$rescoped\";");
  // echo "result: $result\n";
  return $result;
}
*/
