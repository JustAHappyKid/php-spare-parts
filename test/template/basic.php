<?php

require_once 'template/base.php';
use \SpareParts\Template as T;

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

function testNoQuestionMarkNorPhpBracketsAreNecessaryForClosingBracketOnItsOwnLine() {
  $tpl = trim('
    ? if ($myvar) {
      yes!
    }
  ');
  assertEqual('yes!', normalizeSpace(T\renderFromString($tpl, array('myvar' => true))));
  assertEqual('',     normalizeSpace(T\renderFromString($tpl, array('myvar' => false))));
}

function normalizeSpace($s) {
  $lines = array_filter(explode("\n", $s), function($l) { return trim($l) != ''; });
  $linesFixed = array_map(
    function($l) { return preg_replace('/\\s{2,}/', ' ', trim($l)); },
    $lines);
  return implode(' ', $linesFixed);
}
