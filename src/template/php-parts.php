<?php

# XXX: Unused at present; may be useful for further enhancements to template engine though...

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
  function render() { return '<?php ' . $this->content . '?>'; }
}
class PHPOutputPart extends PHPPart {
  function render() { return '<?= ' . $this->content . '?>'; }
}
class TextPart      extends Part {
  function render() { return $this->content; }
}
