<?php

require_once 'template/base.php'; # renderFile, Context
require_once 'string.php';        # contains

use \SpareParts\Template as T;

function testSimpleRenderingOfTemplateFromFile() {
  $context = new T\Context(dirname(__FILE__), array('item1' => 'one', 'item2' => '2',
                                                    'item3' => 'thr33'));
  $result = T\renderFile('template.sphp', $context);
  assertTrue(contains($result, '<li>one</li>'));
  assertTrue(contains($result, '<li>2</li>'));
  assertTrue(contains($result, '<li>thr33</li>'));
}
