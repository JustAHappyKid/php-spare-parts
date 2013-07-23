<?php

namespace SpareParts\Template;

require_once dirname(__FILE__) . '/exceptions.php';
require_once dirname(__FILE__) . '/LineByLineParser.php';

abstract class MethodOrBlock {
  public $name, $body;
  abstract public function generate();
}
class Block  extends MethodOrBlock {
  public function generate() {
    return "function {$this->name}() { ?>\n" . $this->body . "<? }";
  }
}
class Method extends MethodOrBlock {
  public $params;
  public function generate() {
    return "function {$this->name}({$this->params}) {\n" . $this->body . "}";
  }
}

function childTemplateToChildClass(ExpandedTemplate $baseTpl, $tplBody, $pathToTpl) {
  $blocksAndMethods = array();
  $p = new LineByLineParser($tplBody);
  while ($p->moreLinesLeft()) {
    $ln = $p->takeLine();
    if (beginsWith(trim($ln), 'block ')) {
      $parts = explode(' ', $ln);
      if (count($parts) != 3 || $parts[2] != '{') {
        throw new ParseError("Block-definition line did not match expected format: $ln",
                             $pathToTpl, $p->lineNum());
      }
      $block = new Block;
      $block->name = $parts[1];
      if (!isValidBlockName($block->name)) {
        throw new ParseError("`{$block->name}` is an invalid block name",
                             $pathToTpl, $p->lineNum());
      }
//      $ln = $p->takeLine();
//      while (trim($ln) != '}') {
//        $block->body .= "$ln\n";
//        $ln = $p->takeLine();
//      }
      $block->body = takeBody($p);
      $blocksAndMethods []= $block;
    } else if (beginsWith(trim($ln), 'function ')) {
      $m = null;
      preg_match('/^\\s*function\\s+([_0-9a-zA-Z]+)\\s*\\(([^\\)]*)\\)\\s*{$/', $ln, $m);
      if (!$m) throw new ParseError("Function-definition line did not match expected format: $ln",
                                    $pathToTpl, $p->lineNum());
      $method = new Method;
      $method->name = $m[1];
      $method->params = $m[2];
      $method->body = takeBody($p);
      $blocksAndMethods []= $method;
    } else if (trim($ln) != '') {
      throw new ParseError("Expected `block` or `function`", $pathToTpl, $p->lineNum());
    }
  }
  $renderedMethods = array_map(
    function(MethodOrBlock $x) { return $x->generate(); }, $blocksAndMethods);
  $className = uniqueClassName();
  $content = "<?php
    require_once '{$baseTpl->path}';
    class $className extends {$baseTpl->className} {
      " . implode("\n\n", $renderedMethods) . "
    }
  ";
  return saveExpandendTemplate($content, $className);
}

function takeBody(LineByLineParser $parser) {
  $body = '';
  $ln = $parser->takeLine();
  while (trim($ln) != '}') {
    $body .= "$ln\n";
    $ln = $parser->takeLine();
  }
  return $body;
}

function expandBlockReferences($code) {
  $tokens = token_get_all($code);
  $expandedCode = "";
  $blocks = array();
  $i = 0;
  while (count($tokens) > $i) {
    $t = $tokens[$i];
    if (is_array($t)) {
//      echo token_name($t[0]) . ": {$t[1]}\n";
      if ($t[0] == T_STRING && $t[1] == 'block') {
        $t = $tokens[++$i];
        if ($t[0] != T_WHITESPACE) throw new ParseError("Expected whitespace after `block`");
        $t = $tokens[++$i];
        if ($t[0] != T_STRING) throw new ParseError("Expected block name");
        $blockName = $t[1];
        $blocks []= $blockName;
        $expandedCode .= '$this->' . $blockName . '();';
      } else {
        $expandedCode .= $t[1];
      }
    } else {
//      echo "simple string token: {$t}\n";
      $expandedCode .= $t;
    }
    ++$i;
  }
  return array($expandedCode, $blocks);
}

/*
function childTemplateToChildClass(ExpandedTemplate $baseTpl, $tplBody) {
  $lines = explode("\n", $tplBody);
  $fixedLines = array_map(
    function($ln) {
      if (beginsWith(trim($ln), 'block ')) {
        $parts = explode(' ', $ln);
        if (count($parts) != 3 || $parts[2] != '{') {
          throw new ParseError("Block definition line did not match expected format: $ln");
        }
        $name = $parts[1];
        if (!isValidBlockName($name)) {
          throw new ParseError("Invalid block name: $name");
        }
        return "function $name() { ?>";
      } else {
        return $ln;
      }
    },
    $lines);
  $content = "<?php
    class SubTemplate extends {$baseTpl->className} {
      " . implode("\n", $fixedLines) . "
    }
  ";
  return saveExpandendTemplate($content, 'SubTemplate');
}

*/
# TODO: Support any valid PHP function name for block name
function isValidBlockName($name) {
  return preg_match('/^[a-zA-Z]+$/', $name);
}
