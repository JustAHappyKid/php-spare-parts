<?php

require_once 'template/inheritance.php';

use \SpareParts\Template as T;

function testExpandBlockReferences() {
  $r = T\expandBlockReferences("<p>The sub-class says '<?= block sayHello ?>', ya know.</p>");
  list($fixedCode, $blocks) = $r;
  assertTrue(preg_match('/<\\?=\\s*\\$this->sayHello\\(\\s*\\)\\s*;?\\s*\\?>/', $fixedCode) === 1);
  assertEqual('sayHello', $blocks[0]);
}
