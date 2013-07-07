<?php

abstract class Layout {
  abstract function showHeader();
  abstract function realStuff();
  function content() {
    ?><html> <body>
      <? if ($this->showHeader()): ?>
        <div>This is the header :P</div>
      <? endif; ?>
      <div><?= $this->realStuff() ?></div>
    </body> </html><?
  }
}
