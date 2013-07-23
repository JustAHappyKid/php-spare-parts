<?php

namespace SpareParts\Template;

require_once dirname(__FILE__) . '/exceptions.php';       # ParseError
require_once dirname(__FILE__) . '/LineByLineParser.php'; # LineByLineParser

abstract class MethodOrBlock {
  public $name, $body;
  abstract public function generate();
}
class Block  extends MethodOrBlock {
  public function generate() {
    $expanded = expandShorthandPhpVariableSubstitution(
      expandShorthandPhpLogic($this->body));
    $rescoped = rescopeVariables($expanded);
    return "function {$this->name}() { ?>\n" . $rescoped . "<? }";
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
    if (beginsWith($ln, 'block ')) {
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
      $block->body = takeBody($p);
      $blocksAndMethods []= $block;
    } else if (beginsWith($ln, 'function ')) {
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
  while (strlen($ln) == 0 || $ln[0] != '}') {
    $body .= "$ln\n";
    $ln = $parser->takeLine();
  }
  if (trim($ln) != '}') {
    throw new ParseError("Expected line to contain ONLY closing bracket",
                         'TODO report file', $parser->lineNum());
  }
  return $body;
}

function expandBlockReferences($code) {
  $tokens = token_get_all($code);
  $expandedCode = "";
  $blockNames = array();
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
        $blockNames []= $blockName;
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
  return array($expandedCode, $blockNames);
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
