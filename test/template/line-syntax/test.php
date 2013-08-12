<?php

require_once dirname(dirname(__FILE__)) . '/helpers.php';
require_once 'template/base.php';
require_once 'string.php';        # contains

use \SpareParts\Template as T;

function testSupportForSimpleIfStatement() {
  $tpl = "
    ? if (true) {
      <p>Duh.</p>
    }
  ";
  assertEqual('<p>Duh.</p>', renderAndNormalize($tpl, array()));
}

/*
  TODO: Fix support for this...
function testSupportForIfElse() {
  $getTpl = function($switch) { return "
    The heat is...
    ? if ($switch) {
      on.
    } else {
      off.
    }
  "; };
  assertEqual('The heat is... on.',  renderAndNormalize($getTpl(true), array()));
  assertEqual('The heat is... off.', renderAndNormalize($getTpl(false), array()));
}
*/

# The parser should only consider closing-brackets that appear in the same column
# as that which began the given 'block'.
function testMatchingBrackets() {
  $result = renderAndNormalizeTplFile('matching-brackets.diet-php',
    getContext2(array('showAlert' => true)));
  $regex = '/<script .+>\\s*if \\(.+\\) { .+ } else { .+ } <\\/script>/';
  assertTrue(preg_match($regex, $result) === 1);
  /*
  $alertLine = 'alert("You\'re on my site!");';
  assertTrue(contains($result, "(window.location == 'http://mysite.com/') { $alertLine }"));
  */
}

# XXX: Put this file under namespace to avoid name conflicts with 'getContext' ??
function getContext2($vars = array()) {
  return new T\Context(dirname(__FILE__), $vars);
}
