<?php

require_once 'template/base.php';
require_once 'string.php';        # contains

use \SpareParts\Template as T;

/*
function testXXXDebugging() {
  $context = new T\Context(dirname(__FILE__), array());
  $result = T\compileFile('test1.page.diet-php', $context);
  foreach (glob('/tmp/spare-parts*') as $f) {
    echo "$f:\n";
    echo "-------------------------------------------------------------------\n";
    echo file_get_contents($f) . "\n";
    echo "-------------------------------------------------------------------\n\n";
    unlink($f);
  }
}
*/

function testBasicInheritance() {
  $context = getContext();
  $result = T\renderFile('test1.page.diet-php', $context);
  assertTrue(contains($result, "isn't this great?</p>"));
  assertTrue(contains($result, '<p>Two paragraphs, and the layout'));
}

function testExtendingFromPurePHPClass() {
  $context = getContext();
  $result = T\renderFile('extend-php-class.diet-php', $context);
  assertTrue(contains($result, '<div id="content">'));
  assertTrue(contains($result, 'Welcome to my simple webpage.'));
}

function testSupportForLogicEmbeddedWithinBlock() {
  $context = getContext(array('loggedIn' => true, 'name' => 'Fred'));
  $result = T\renderFile('embedded-logic.diet-php', $context);
  assertTrue(contains($result, "Welcome Fred!"));
}

function getContext($vars = array()) {
  return new T\Context(dirname(__FILE__), $vars);
}

/*
function testSupportForTemplateInheritance() {
  $context = new T\Context(dirname(__FILE__), array());
  $result = T\renderFile('page.diet-php', $context);
  assertTrue(contains($result, '<p>Two paragraphs, and the layout'));
  assertTrue(preg_match('@<title>\\s*Welcome to the website!\\s*</title>@', $result) == 1);
}
*/
