<?php

require_once dirname(__FILE__) . '/helpers.php';  # renderAndNormalize

function testShorthandLineSyntaxWithVariable() {
  $tpl = '
    ? if ($myvar) {
      yes!
    }
  ';
  assertEqual('yes!', renderAndNormalize($tpl, array('myvar' => true)));
  assertEqual('',     renderAndNormalize($tpl, array('myvar' => false)));
}
