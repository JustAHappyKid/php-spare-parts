<?php

require_once 'template/base.php';
use \SpareParts\Template as T;

function testSimpleVariableSubstitution() {
  $sampleVars = array('yoyo', 'x', 'v1', 'mIxeDcASe', 'camelCase', 'ALLCAPS', 'with_underscore');
  foreach ($sampleVars as $varName) {
    $tpl = '<p>$' . $varName . '</p>';
    $r = T\renderString($tpl, array($varName => 'The goods.'));
    assertEqual('<p>The goods.</p>', $r);
  }
}

function testLiteralDollarAmountStringIsNotConstruedAsVariable() {
  $r = T\renderString('<p>It costs $500.</p>', array());
  assertEqual('<p>It costs $500.</p>', $r);
}

function testTemplateThatContainsNothingButAVariable() {
  assertEqual('bueno', T\renderString('$justThis', array('justThis' => 'bueno')));
}

function testSupportForVarEmbeddedInStringInPhpBlock() {
  $tpl = '<?php $css = "width: $theWidth%;"; ?>$css';
  $r = T\renderString($tpl, array('theWidth' => 75));
  assertEqual('width: 75%;', $r);
}

function testSupportForLocalVariables() {
  $tpl = implode("\n", array(
    '<?php $total = $v1 + $v2; ?>',
    'The total is $total.'));
  $r = T\renderString($tpl, array('v1' => 100, 'v2' => 4));
  assertEqual('The total is 104.', $r);
}

function testSupportForReferencingObjectAttributes() {
  class MyLittleClass { public $myAttribute; }
  $o = new MyLittleClass;
  $o->myAttribute = 'my little string';
  $tpl = 'The man said, "$man->myAttribute"';
  assertEqual('The man said, "my little string"', T\renderString($tpl, array('man' => $o)));
}

function testSupportForObjectOperatorIsNotOverzealous() {
  $tpl = '<span>$newVar<!--$oldVar--></span>';
  T\renderString($tpl, array('oldVar' => 'in the comment', 'newVar' => 'this is it'));
}
