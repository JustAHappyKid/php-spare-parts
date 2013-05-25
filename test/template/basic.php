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

function testQuestionMarkForIndicatingLineOfPHP() {
  $tpl = trim("
    ? if (true) {
      <p>Duh.</p>
    ? } else {
      <p>Nevah gunna c this.</p>
    ? }
  ");
  $r = T\renderFromString($tpl, array());
  assertEqual('<p>Duh.</p>', trim($r));
}

function testVariableSubstitutionWithQuestionMarkSyntax() {
  $tpl = trim('
    The heat is...
    ? if ($switch) {
      on.
    ? } else {
      off.
    ? }
  ');
  assertEqual('The heat is... on.',
    normalizeSpace(T\renderFromString($tpl, array('switch' => true))));
  assertEqual('The heat is... off.',
    normalizeSpace(T\renderFromString($tpl, array('switch' => false))));
}

function normalizeSpace($s) {
  $lines = explode("\n", $s);
  $linesFixed = array_map(
    function($l) { return preg_replace('/\\s{2,}/', ' ', trim($l)); },
    $lines);
  return implode(' ', $linesFixed);
}
