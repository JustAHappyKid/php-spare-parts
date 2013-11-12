<?php

# The idea with this file is to provide the *most useful* functions in the global
# namespace. These would be functions that are likely to be used in the majority of
# PHP scripts for the average application.

function at($arr, $index, $default = null) {
  require_once dirname(__FILE__) . '/array.php';
  return SpareParts\ArrayLib\at($arr, $index, $default);
}

function head(Array $arr) {
  require_once dirname(__FILE__) . '/array.php';
  return SpareParts\ArrayLib\head($arr);
}
