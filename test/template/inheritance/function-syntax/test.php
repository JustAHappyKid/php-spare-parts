<?php

require_once 'template/base.php';
require_once 'string.php';        # contains

use \SpareParts\Template as T;

function testImplementingMethodFromDietPhpSubTemplate() {
  $context = new T\Context(dirname(__FILE__), array());
  $result = T\renderFile('page.diet-php', $context);
  assertTrue(contains($result, 'This is it.'));
}
