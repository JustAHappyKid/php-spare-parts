<?php

require_once 'webapp/base-controller.php';

class AController extends \SpareParts\Webapp\Controller {

  # A number of dummy "magic methods", so we can assert (in test case) no requests will
  # be routed to them.
  function __construct() { $this->someVar = 'initialized'; }
  function __toString() { "hi from AController"; }
  function __isset($v) { return false; }

  # This is just a dummy method so we can assert (in test case) that it cannot be
  # accessed directly via a request.
  function init() {
    # Init some stuff - ok...
  }

  function twoWords() {
    return 'Sun Shine';
  }

  # A pretend private helper-method, so we can ensure the router will not route a
  # request here.
  private function getSomething() { return 'something'; }

  # And a pretend protected helper-method...
  protected function getOther() { return array('other'); }
}

return 'AController';
