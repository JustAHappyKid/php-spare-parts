<?php

namespace SpareParts\Template;

require_once dirname(dirname(__FILE__)) . '/string.php';      # beginsWith, withoutPrefix, ...
require_once dirname(dirname(__FILE__)) . '/reflection.php';  # getImplementations, ...

require_once dirname(__FILE__) . '/exceptions.php';       # NoSuchTemplate, SecurityException, ...
require_once dirname(__FILE__) . '/Renderable.php';       # Renderable
require_once dirname(__FILE__) . '/shorthand-php.php';    # expandShorthandPhpLogic, ...
require_once dirname(__FILE__) . '/variable-scope.php';   # rescopeVariables
require_once dirname(__FILE__) . '/inheritance.php';      # expandBlockReferences, ...

use \InvalidArgumentException, \Closure, \SpareParts\Reflection;

function renderString($tplContent, Array $vars) {
  $t = generateClassForBaseTemplate($tplContent, $vars);
  return renderExpandedTemplateAndUnlinkTmpFile($t, $vars);
}

/** @deprecated Use 'renderString' instead. */
function renderFromString($tplContent, Array $vars) {
  return renderString($tplContent, $vars);
}

function renderFile($path, Context $context) {
  $t = produceRenderableFromFile($path, $context);
  return renderExpandedTemplateAndUnlinkTmpFile($t, $context->vars);
}

function renderExpandedTemplateAndUnlinkTmpFile(ExpandedTemplate $t, Array $vars) {
  require $t->path;
  $obj = new $t->className;
  $out = renderTemplate($obj, $vars);
  unlink($t->path);
  $t->path = null;
  return $out;
}

function renderTemplate(Renderable $tpl, $vars) {
  return captureOutput(function() use($tpl, $vars) { $tpl->__render($vars); });
}

/**
 * If $path is a simple PHP file, then it's loaded (via `require`) and is expected
 * to have an implementation of Renderable. Otherwise, if $path is a Diet PHP file,
 * it is compiled to a Renderable. In either case, an instance of the Renderable
 * implementation is returned.
 * @return ExpandedTemplate
 */
function produceRenderableFromFile($path, Context $context) {
  if (contains($path, '..')) throw new SecurityException("No .. allowed");
  if (endsWith($path, '.php')) {
    $iface = 'SpareParts\\Template\\Renderable';
    $absPath = $context->baseDir . '/' . $path;
    if (!is_readable($absPath)) throw new NoSuchTemplate($path);
    require_once $absPath;
    $all = Reflection\getClassesDefinedInFile($absPath);
    $impls = array_filter($all,
      function($cls) use($iface) { return Reflection\implementsInterface($cls, $iface); });
    if (count($impls) == 0) {
      throw new InvalidArgumentException("No implementations of $iface are " .
                                         "defined in file '{$path}'");
    } else if (count($impls) > 1) {
      throw new InvalidArgumentException("Multpile implementations of $iface are defined " .
                                         "in file '{$path}''");
    } else {
      return new ExpandedTemplate($absPath, current($impls));
    }
  } else if (endsWith($path, '.diet-php')) {
    return compileFile($path, $context);
  } else {
    throw new InvalidArgumentException(
      "Given \$path did not have extension `.php` nor `.diet-php`");
  }
}

function compileFile($path, Context $context) {
  if (contains($path, '..')) throw new SecurityException("No .. allowed");
  return generateClassFromTemplateFile($path, $context);
}

/** @return ExpandedTemplate */
function generateClassFromTemplateFile($relPath, Context $context) {
  $absPath = "{$context->baseDir}/$relPath";
  if (!is_readable($absPath)) throw new NoSuchTemplate($absPath);
  $content = file_get_contents($absPath);
  if (beginsWith($content, '!extends')) {
    # XXX: What if we came across a file with just one line here??
    list($extendStmnt, $blocks) = explode("\n", $content, 2);
    $quotedFname = trim(withoutPrefix($extendStmnt, '!extends'));
    $parentTplFile = trim($quotedFname, "'\"");
    $parentTpl = produceRenderableFromFile($parentTplFile, $context);
//    $parentTpl = generateClassFromTemplateFile($parentTplFile, $context);
    return childTemplateToChildClass($parentTpl, $context->vars, $blocks, $relPath);
    // $expanded = expandShorthandPHP($blocks);
    // return saveMethodsAsClass($expanded);
  } else {
    return generateClassForBaseTemplate($content, $context->vars);
  }
}

function generateClassForBaseTemplate($content, Array $vars) {
  $expanded = expandShorthandPhpVariableSubstitution(
    expandShorthandPhpLogic($content));
  list($blocksFixed, $_) = expandBlockReferences($expanded);
  $rescoped = rescopeVariables($blocksFixed, $vars);
  return saveAsRenderableClass($rescoped);
}

function saveAsRenderableClass($content) {
  return saveMethodsAsClass('
    public function __render($vars) {
      $this->__vars = $vars;
      ?>' . $content . '<?php
    }');
}

function saveMethodsAsClass($methods) {
  $className = uniqueClassName();
  $content = "<?php
    class $className implements \\SpareParts\\Template\\Renderable {
      $methods
    }
  ";
  return saveExpandedTemplate($content, $className);
}

function uniqueClassName() {
  return uniqid("sparePartsTpl");
}

function saveExpandedTemplate($content, $className) {
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
