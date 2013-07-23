<?php

require_once 'template/base.php';
use \SpareParts\Template as T;

function testLineEndingsAreMaintained() {
  $tpl = implode("\n", array(
    '<body>',
    '<p>this is my site.</p>',
    '</body>'));
  $r = T\renderFromString($tpl, array());
  $lines = explode("\n", $r);
  assertEqual(3, count($lines));
  assertEqual('<p>this is my site.</p>', $lines[1]);
}
