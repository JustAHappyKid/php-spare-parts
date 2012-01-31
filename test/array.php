<?php

require_once 'array.php';

function testFlatten() {
  assertEqual(array(2), flatten(array(0 => array(2))));
}
