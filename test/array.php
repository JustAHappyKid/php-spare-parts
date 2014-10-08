<?php

require_once 'array.php';
use \SpareParts\ArrayLib;

function testHeadDoesNotCareIfFirstElementHasNonZeroIndex() {
  $a = array('a', 'b', 'c');
  assertEqual('a', ArrayLib\head($a));
  unset($a[0]);
  assertEqual('b', ArrayLib\head($a));
  unset($a[1]);
  assertEqual('c', ArrayLib\head($a));
}

function testTail() {
  assertEqual(array(2), ArrayLib\tail(array(1, 2)));
  assertEqual(array(90, 80, 50, 30, 0), ArrayLib\tail(array(100, 90, 80, 50, 30, 0)));
  assertEqual(array(), ArrayLib\tail(array(999)));
  assertThrows('InvalidArgumentException', function() { ArrayLib\tail(array()); });
}

function testFilterByKey() {
  $a = array('looooong' => 'keep me!', 'shrt' => 'not me?');
  $q = function($k) { return strlen($k) > 5; };
  assertEqual(array('looooong' => 'keep me!'), ArrayLib\filterByKey($q, $a));
}

function testFlatten() {
  assertEqual(array(2), ArrayLib\flatten(array(0 => array(2))));
}

function testCommonPrefixForArrays() {
  assertEqual(array('h'),
    ArrayLib\commonPrefix(array(
      array('h', 'e', 'l', 'l', 'o'), array('h', 'i'))));
  assertEqual(array("home", "johnny", "appleseeds"),
    ArrayLib\commonPrefix(array(
      array("home", "johnny", "appleseeds", "red", "pink-lady"),
      array("home", "johnny", "appleseeds", "red", "delicious"),
      array("home", "johnny", "appleseeds", "green", "green-apple"))));
  assertThrows('InvalidArgumentException', function() { ArrayLib\commonPrefix(array()); });
}
