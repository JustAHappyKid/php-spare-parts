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
  assertEqual('<p>Duh.</p>', renderAndNormalize($tpl, array()));
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
  assertEqual('The heat is... on.',  renderAndNormalize($tpl, array('switch' => true)));
  assertEqual('The heat is... off.', renderAndNormalize($tpl, array('switch' => false)));
}

function testNoQuestionMarkNorPhpBracketsAreNecessaryForClosingBracketOnItsOwnLine() {
  $tpl = trim('
    ? if ($myvar) {
      yes!
    }
  ');
  assertEqual('yes!', renderAndNormalize($tpl, array('myvar' => true)));
  assertEqual('',     renderAndNormalize($tpl, array('myvar' => false)));
}

/**
 * Render the given template (string), using given $vars, and normalize spaces
 * (as, in these particular test-cases, we aren't concerned with specific whitespace).
 */
function renderAndNormalize($tpl, Array $vars) {
  return normalizeSpace(T\renderString($tpl, $vars));
}

function normalizeSpace($s) {
  $lines = array_filter(explode("\n", $s), function($l) { return trim($l) != ''; });
  $linesFixed = array_map(
    function($l) { return preg_replace('/\\s{2,}/', ' ', trim($l)); },
    $lines);
  return implode(' ', $linesFixed);
}
