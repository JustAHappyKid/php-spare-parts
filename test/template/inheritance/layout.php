<?php

require_once 'template/Renderable.php';
use \SpareParts\Template\Renderable;

abstract class Layout implements Renderable {

  abstract function realStuff();

  function content() {
    ?><html> <body>
      <div id="content"><?= $this->realStuff() ?></div>
    </body> </html><?php
  }

  function __render($vars) { return $this->content(); }
}
