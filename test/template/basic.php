<?php

require_once dirname(__FILE__) . '/helpers.php';  # renderAndNormalize

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
