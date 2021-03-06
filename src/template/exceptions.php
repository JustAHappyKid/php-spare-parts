<?php

namespace SpareParts\Template;

use \Exception;

class TemplateException extends Exception {}

class SecurityException extends TemplateException {}

class NoSuchTemplate    extends TemplateException {
  private $path;
  function __construct($path) {
    $this->path = $path;
    $this->message = "No file exists at $path";
  }
}

class ParseError        extends TemplateException {
  function __construct($msg, $tplFile, $lineNum) {
    $this->message = "$tplFile, line $lineNum: $msg";
  }
}

class UndefinedVariable extends TemplateException {
  private $varName;
  function __construct($varName) {
    $this->varName = $varName;
    $this->message = "No value specified for variable \${$varName}";
  }
}
