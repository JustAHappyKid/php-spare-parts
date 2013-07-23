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

function testLiteralDollarAmountStringIsNotConstruedAsVariable() {
  $r = T\renderFromString('<p>It costs $500.</p>', array());
  assertEqual('<p>It costs $500.</p>', $r);
}

function testSupportForVarEmbeddedInStringInPhpBlock() {
  $tpl = '<?php $css = "width: $theWidth%;"; ?>$css';
  $r = T\renderFromString($tpl, array('theWidth' => 75));
  assertEqual('width: 75%;', $r);
}

function testSupportForLocalVariables() {
  $tpl = implode("\n", array(
    '<?php $total = $v1 + $v2; ?>',
    'The total is $total.'));
  $r = T\renderFromString($tpl, array('v1' => 100, 'v2' => 4));
  assertEqual('The total is 104.', $r);
}
