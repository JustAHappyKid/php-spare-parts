<?php

function flatten(Array $origArray) {
  $flatArray = array();
  foreach ($origArray as $a) $flatArray = array_merge($flatArray, $a);
  return $flatArray;
}
