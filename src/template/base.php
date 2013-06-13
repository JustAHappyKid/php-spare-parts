<?php

namespace SpareParts\Template;

require_once dirname(dirname(__FILE__)) . '/array.php';
require_once dirname(dirname(__FILE__)) . '/string.php';  # beginsWith, withoutPrefix, ...

use \Closure, \Exception, \SpareParts\ArrayLib as A;

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

class Context {
  public $baseDir, $vars;
  function __construct($baseDir, Array $vars) {
    $this->baseDir = $baseDir;
    $this->vars = $vars;
  }
}

class TemplateException extends Exception {}
class SecurityException extends TemplateException {}
class NoSuchTemplate extends TemplateException {}

function renderFile($path, Context $context) {
  if (contains($path, '..')) throw new SecurityException("No .. allowed");
  $t = generateClassFromTemplate($path, $context);
  require $t->path;
  $obj = new $t->className;
  return captureOutput(function() use($obj, $context) { $obj->__render($context->vars); });
  //return renderFromString($tpl, $context->vars);
}

function generateClassFromTemplate($relPath, Context $context) {
  $absPath = "{$context->baseDir}/$relPath";
  if (!is_readable($absPath)) throw new NoSuchTemplate($absPath);
  $content = file_get_contents($absPath);
  if (beginsWith($content, 'extends')) {
    # XXX: What if we came across a file with just one line here??
    list($extendStmnt, $classMethods) = explode("\n", $content, 2);
    $quotedFname = trim(withoutPrefix($extendStmnt, 'extends'));
    $parentTplFile = trim($quotedFname, "'\"");
    generateClassFromTemplate($parentTplFile, $context);
    $expanded = expandShorthandPHP($classMethods);
    /* return saveMethodsAsClass(withoutPrefix(withoutSuffix($expanded, '?>'), '<?')); */
    return saveMethodsAsClass($expanded);
  } else {
    // xxx;
    $expanded = expandShorthandPHP($content);
    return saveAsRenderableClass($expanded);
  }
}

class ExpandedTemplate {
  public $path, $className;
  function __construct($p, $cn) {
    $this->path = $p; $this->className = $cn;
  }
}

function saveAsRenderableClass($content) {
  return saveMethodsAsClass('public function __render($vars) { ?>' . $content . '<? }');
}

function saveMethodsAsClass($methods) {
  $content = "<?php
    class Whatev {
      $methods
    }
  ";
  $tmpF = tempnam(sys_get_temp_dir(), 'spare-parts-tpl');
  //echo "wanna save the following to file $tmpF...\n" . $content . "\n";
  file_put_contents($tmpF, $content);
  return new ExpandedTemplate($tmpF, 'Whatev');
}

function renderFromString($tpl, $vars) {
  $final = expandShorthandPHP($tpl);
  ob_start();
  eval("?>$final");
  $rendered = ob_get_contents();
  ob_end_clean();
  return $rendered;
}

function expandShorthandPHP($tpl) {
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
  return $final;
}

function captureOutput(Closure $f) {
  ob_start();
  $f();
  $output = ob_get_contents();
  ob_end_clean();
  return $output;
}
