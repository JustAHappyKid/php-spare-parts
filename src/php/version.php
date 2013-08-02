<?php

namespace SpareParts\PHP;

use \Exception;

function getVersion() {
  $v = phpversion();
  $ps = explode('.', $v, 3);
  /*
  if (count($ps) != 3) {
    throw new Exception("'phpversion' returned unexpected value: $v");
  }
  */
  return new Version((int)$ps[0], (int)$ps[1], $ps[2]);
}

class Version {
  public $major, $minor, $release;
  function __construct($ma, $mi, $r) {
    list($this->major, $this->minor, $this->release) = array($ma, $mi, $r);
  }
}
