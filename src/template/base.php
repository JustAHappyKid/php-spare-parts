<?php

namespace SpareParts\Template;

require_once dirname(dirname(__FILE__)) . '/string.php';  # beginsWith, withoutPrefix, ...
require_once dirname(__FILE__) . '/exceptions.php';       # NoSuchTemplate, SecurityException, ...
require_once dirname(__FILE__) . '/shorthand-php.php';    # expandShorthandPhpLogic, ...
require_once dirname(__FILE__) . '/variable-scope.php';   # rescopeVariables
require_once dirname(__FILE__) . '/inheritance.php';      # expandBlockReferences, ...

use \Closure;


function renderFromString($tplContent, $vars) {
//  $expanded = expandShorthandPhpVariableSubstitution(
//    expandShorthandPhpLogic($tpl));
//  $final = rescopeVariables($expanded);
//  ob_start();
  /*eval("?>$final");*/
//  $rendered = ob_get_contents();
//  ob_end_clean();
//  return $rendered;
  $t = generateClassForBaseTemplate($tplContent);
  require $t->path;
  $renderable = new $t->className;
  return renderTemplate($renderable, $vars);
}

function renderFile($path, Context $context) {
  $t = compileFile($path, $context);
  require $t->path;
  $obj = new $t->className;
  return renderTemplate($obj, $context->vars);
}

function compileFile($path, Context $context) {
  if (contains($path, '..')) throw new SecurityException("No .. allowed");
  return generateClassFromTemplateFile($path, $context);
}

function generateClassFromTemplateFile($relPath, Context $context) {
  $absPath = "{$context->baseDir}/$relPath";
  if (!is_readable($absPath)) throw new NoSuchTemplate($absPath);
  $content = file_get_contents($absPath);
  if (beginsWith($content, '!extends')) {
    # XXX: What if we came across a file with just one line here??
    list($extendStmnt, $blocks) = explode("\n", $content, 2);
    $quotedFname = trim(withoutPrefix($extendStmnt, '!extends'));
    $parentTplFile = trim($quotedFname, "'\"");
    $parentTpl = generateClassFromTemplateFile($parentTplFile, $context);
    return childTemplateToChildClass($parentTpl, $blocks);
    // $expanded = expandShorthandPHP($blocks);
    // return saveMethodsAsClass($expanded);
  } else {
    return generateClassForBaseTemplate($content);
  }
}

function renderTemplate(Renderable $tpl, $vars) {
  return captureOutput(function() use($tpl, $vars) { $tpl->__render($vars); });
}

function generateClassForBaseTemplate($content) {
  $expanded = expandShorthandPhpVariableSubstitution(
    expandShorthandPhpLogic($content));
  list($blocksFixed, $blocks) = expandBlockReferences($expanded);
  $rescoped = rescopeVariables($blocksFixed);
  return saveAsRenderableClass($rescoped, $blocks);
}

function saveAsRenderableClass($content) {
  return saveMethodsAsClass('public function __render($vars) { ?>' . $content . '<? }');
}

function saveMethodsAsClass($methods) {
  $className = uniqid("sparePartsTpl");
  $content = "<?php
    class $className extends \\SpareParts\\Template\\Renderable {
      $methods
    }
  ";
  return saveExpandendTemplate($content, $className);
}

function saveExpandendTemplate($content, $className) {
  $tmpF = tempnam(sys_get_temp_dir(), 'spare-parts-tpl');
  file_put_contents($tmpF, $content);
  return new ExpandedTemplate($tmpF, $className);
}

function captureOutput(Closure $f) {
  ob_start();
  $f();
  $output = ob_get_contents();
  ob_end_clean();
  return $output;
}

class Context {
  public $baseDir, $vars;
  function __construct($baseDir, Array $vars) {
    $this->baseDir = $baseDir;
    $this->vars = $vars;
  }
}

class ExpandedTemplate {
  public $path, $className;
  function __construct($p, $cn) {
    $this->path = $p; $this->className = $cn;
  }
}

abstract class Renderable {
  abstract public function __render($vars);
}
