<?php

namespace SpareParts\System\CommandLineArgs;

require_once dirname(dirname(__FILE__)) . '/string.php';

function separateArgsAndSwitches($argv) {
  $r = new ArgsAndSwitches;
  foreach ($argv as $a) {
    if (beginsWith($a, "-")) $r->switches []= $a;
    else $r->baseArguments []= $a;
  }
  return $r;
}

class ArgsAndSwitches {
  public $baseArguments = array(), $switches = array();
}
