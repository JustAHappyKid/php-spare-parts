<?php

require_once 'template/Renderable.php';
use \SpareParts\Template\Renderable;

abstract class LayoutWithFunction implements Renderable {

  abstract function showSomething();

  function theDocument() {
    ?><html> <body>
      <?= $this->showSomething() ? 'This is it.' : '(nothing here.)' ?>
    </body> </html><?php
  }

  function __render($vars) { return $this->theDocument(); }
}
