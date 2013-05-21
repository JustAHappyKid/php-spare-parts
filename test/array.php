<?php

require_once 'array.php';
use \SpareParts\ArrayLib;

function testFlatten() {
  assertEqual(array(2), ArrayLib\flatten(array(0 => array(2))));
}
