<?php

require_once 'template/base.php';
use \SpareParts\Template as T;

function testSimpleVariableSubstitution() {
  $sampleVars = array('yoyo', 'x', 'v1', 'mIxeDcASe', 'camelCase', 'ALLCAPS', 'with_underscore');
  foreach ($sampleVars as $varName) {
    $tpl = '<p>$' . $varName . '</p>';
    $r = T\renderFromString($tpl, array($varName => 'The goods.'));
    assertEqual('<p>The goods.</p>', $r);
  }
}
