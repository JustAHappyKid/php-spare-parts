<?php

require_once 'array.php';
use \SpareParts\ArrayLib;

function testFilterByKey() {
  $a = array('looooong' => 'keep me!', 'shrt' => 'not me?');
  $q = function($k) { return strlen($k) > 5; };
  assertEqual(array('looooong' => 'keep me!'), ArrayLib\filterByKey($q, $a));
}

function testFlatten() {
  assertEqual(array(2), ArrayLib\flatten(array(0 => array(2))));
}
